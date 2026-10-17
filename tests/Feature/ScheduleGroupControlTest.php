<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Wl35DeviceMedia;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleGroupControlTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatecloud.base_url' => 'http://cloud.test', 'privatecloud.token' => 'test',
            'unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.fallback_base_url' => '',
            'unifiedfleet.token' => 'test']);
        Http::preventStrayRequests();
    }

    private function members(?Group $group, string $mac, string $id): Device
    {
        Wl35DeviceProfile::create(['device_id' => $id, 'name' => $id, 'group_id' => $group?->id]);

        return Device::factory()->create(['mac_address' => $mac, 'group_id' => $group?->id]);
    }

    private function schedule(array $attributes = []): Schedule
    {
        return Schedule::create(array_replace(['name' => 'Control', 'type' => 'power_off',
            'scheduled_at' => now()->subMinute(), 'status' => 'pending', 'recurrence_type' => 'once'], $attributes));
    }

    private function ack(string $id, bool $success = true): array
    {
        return ['key' => 'wl35:'.$id, 'type' => 'wl35', 'id' => $id, 'success' => $success];
    }

    private function fakeCommands(?callable $gateway = null, ?callable $cloud = null): void
    {
        Http::fake(function (Request $request) use ($gateway, $cloud) {
            if (str_starts_with($request->url(), 'http://cloud.test/')) {
                return $cloud ? $cloud($request) : Http::response(['result' => 0, 'device' => ['playlist' => []]]);
            }
            if (str_ends_with($request->url(), '/api/health')) {
                return Http::response(['ok' => true]);
            }
            $this->assertSame('http://gateway.test/api/fleet/commands', $request->url());

            return $gateway ? $gateway($request) : Http::response(['results' => [
                $this->ack(substr($request['targets'][0], 5)),
            ]]);
        });
    }

    private function assertRecipients(array $expected): void
    {
        $actual = [];
        foreach (Http::recorded() as [$request]) {
            if ($request->method() !== 'POST') {
                continue;
            }
            if ($request->url() === 'http://gateway.test/api/fleet/commands') {
                array_push($actual, ...$request['targets']);
            } else {
                $this->assertMatchesRegularExpression('~^http://cloud.test/api/devices/([A-Z0-9]+)/(power|format-sd)$~', $request->url());
                $actual[] = 'z2:'.explode('/', $request->url())[5];
            }
        }
        sort($actual);
        sort($expected);
        $this->assertSame($expected, $actual);
    }

    #[DataProvider('controlFlows')]
    public function test_new_and_edited_mixed_group_controls_never_reach_outsiders(string $type, bool $edit, bool $manual): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
        $selected = Group::factory()->create();
        $outside = Group::factory()->create();
        $this->members($selected, 'AA:BB', 'selected-a');
        $this->members($selected, 'CCDD', 'selected-b');
        Device::factory()->create(['group_id' => $selected->id, 'mac_address' => 'aabb']);
        $this->members($outside, 'EEEE', 'outside');
        $this->members(null, 'FFFF', 'ungrouped');
        $payload = ['name' => 'Group control', 'type' => $type, 'target_scope' => 'group',
            'group_id' => $selected->id, 'device_id' => null, 'recurrence_type' => 'once',
            'scheduled_at' => now()->subMinute()->format('Y-m-d H:i:s')];
        if ($edit) {
            $schedule = $this->schedule(['target_scope' => 'group', 'group_id' => $outside->id]);
            $this->put('/schedules/'.$schedule->id, $payload)->assertRedirect()->assertSessionHasNoErrors();
        } else {
            $this->post('/schedules', $payload)->assertRedirect()->assertSessionHasNoErrors();
            $schedule = Schedule::sole();
        }
        $this->assertSame($selected->id, $schedule->fresh()->group_id);
        $this->assertSame('group', $schedule->fresh()->target_scope);
        $this->fakeCommands();
        if ($manual) {
            $this->post('/schedules/'.$schedule->id.'/execute')->assertRedirect();
        } else {
            $this->artisan('schedules:process')->assertSuccessful();
        }
        $this->assertSame('executed', $schedule->fresh()->status);
        $this->assertRecipients(['z2:AABB', 'z2:CCDD', 'wl35:selected-a', 'wl35:selected-b']);
        foreach (Http::recorded() as [$request]) {
            if ($request->method() !== 'POST') {
                continue;
            }
            if (str_contains($request->url(), 'gateway.test')) {
                $this->assertSame($type === 'format_sd' ? 'format_sd' : 'power', $request['command']);
                if ($type !== 'format_sd') {
                    $this->assertSame($type === 'power_on', $request['value']);
                }
            } elseif ($type !== 'format_sd') {
                $this->assertSame($type === 'power_on' ? 1 : 0, $request['value']);
            } else {
                $this->assertStringEndsWith('/format-sd', $request->url());
            }
        }
    }

    public static function controlFlows(): array
    {
        $cases = [];
        foreach (['power_on', 'power_off', 'format_sd'] as $type) {
            foreach ([false, true] as $edit) {
                foreach ([false, true] as $manual) {
                    $cases[$type.'-'.($edit ? 'edit' : 'create').'-'.($manual ? 'manual' : 'auto')] = [$type, $edit, $manual];
                }
            }
        }

        return $cases;
    }

    public function test_membership_is_resolved_at_execution_including_35_only_groups(): void
    {
        $selected = Group::factory()->create();
        $outside = Group::factory()->create();
        $device = $this->members($selected, 'AABB', 'fan-a');
        $this->members($outside, 'CCDD', 'fan-b');
        $schedule = $this->schedule(['target_scope' => 'group', 'group_id' => $selected->id]);
        $device->update(['group_id' => $outside->id]);
        Wl35DeviceProfile::where('device_id', 'fan-b')->update(['group_id' => $selected->id]);
        $this->fakeCommands();
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertRecipients(['wl35:fan-a', 'wl35:fan-b']);
        $this->assertSame('executed', $schedule->fresh()->status);
    }

    public function test_only_explicit_all_reaches_every_registered_device(): void
    {
        $this->members(Group::factory()->create(), 'AABB', 'fan-a');
        $this->members(null, 'CCDD', 'fan-b');
        $schedule = $this->schedule(['target_scope' => 'all']);
        $this->fakeCommands();
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertRecipients(['z2:AABB', 'z2:CCDD', 'wl35:fan-a', 'wl35:fan-b']);
        $this->assertSame('executed', $schedule->fresh()->status);
    }

    #[DataProvider('unsafeScopes')]
    public function test_empty_deleted_or_ambiguous_scope_never_broadcasts(string $case): void
    {
        $this->members(null, 'AABB', 'outside');
        $group = Group::factory()->create();
        $schedule = $this->schedule($case === 'ambiguous' ? [] : ['target_scope' => 'group', 'group_id' => $group->id]);
        if ($case === 'deleted') {
            $group->forceDelete();
        }
        $this->fakeCommands();
        $this->artisan('schedules:process')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertSame('failed', $schedule->fresh()->status);
        $this->assertNotEmpty($schedule->fresh()->last_error);
        if ($case !== 'empty') {
            $this->assertStringContainsString('Destino sin confirmar', $schedule->fresh()->last_error);
        }
    }

    public static function unsafeScopes(): array
    {
        return [['empty'], ['deleted'], ['ambiguous']];
    }

    public function test_invalid_identifiers_fail_without_skipping_valid_members(): void
    {
        $group = Group::factory()->create();
        $this->members($group, 'AABB', 'valid');
        $this->members($group, '../all', 'bad/key');
        $this->members($group, '', ' ');
        $schedule = $this->schedule(['target_scope' => 'group', 'group_id' => $group->id]);
        $this->fakeCommands();
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertRecipients(['z2:AABB', 'wl35:valid']);
        $this->assertSame('failed', $schedule->fresh()->status);
    }

    #[DataProvider('badAcks')]
    public function test_malformed_or_partial_35_ack_fails_and_never_clears_other_mappings(array $results): void
    {
        $group = Group::factory()->create();
        $this->members($group, 'AABB', 'fan-a');
        Wl35DeviceProfile::create(['device_id' => 'fan-b', 'name' => 'Fan B', 'group_id' => $group->id]);
        foreach (['fan-a', 'fan-b', 'outside'] as $id) {
            Wl35DeviceMedia::create(['device_id' => $id, 'video_index' => 1, 'filename' => 'keep.mp4']);
        }
        $schedule = $this->schedule(['type' => 'format_sd', 'target_scope' => 'group', 'group_id' => $group->id]);
        $this->fakeCommands(fn (Request $request) => Http::response(['results' => $request['targets'] === ['wl35:fan-a'] ? $results : [$this->ack('fan-b')]]));
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertRecipients(['z2:AABB', 'wl35:fan-a', 'wl35:fan-b']);
        $this->assertSame('failed', $schedule->fresh()->status);
        $this->assertSame(['fan-a', 'outside'], Wl35DeviceMedia::orderBy('device_id')->pluck('device_id')->all());
    }

    public static function badAcks(): array
    {
        $valid = ['key' => 'wl35:fan-a', 'id' => 'fan-a', 'type' => 'wl35', 'success' => true];

        return [
            'missing' => [[]],
            'wrong target' => [[array_replace($valid, ['key' => 'wl35:outside'])]],
            'wrong id' => [[array_replace($valid, ['id' => 'outside'])]],
            'wrong type' => [[array_replace($valid, ['type' => 'z2'])]],
            'truthy success' => [[array_replace($valid, ['success' => 1])]],
            'rejected' => [[array_replace($valid, ['success' => false])]],
            'unexpected extra' => [[$valid, array_replace($valid, ['id' => 'outside', 'key' => 'wl35:outside'])]],
            'duplicate' => [[$valid, $valid]],
            'scalar row' => [['invalid']],
        ];
    }

    public function test_independent_cloud_and_gateway_failures_do_not_skip_later_recipients(): void
    {
        $group = Group::factory()->create();
        $this->members($group, 'AABB', 'fan-a');
        $this->members($group, 'CCDD', 'fan-b');
        $schedule = $this->schedule(['target_scope' => 'group', 'group_id' => $group->id]);
        $this->fakeCommands(
            fn (Request $request) => $request['targets'] === ['wl35:fan-a']
                ? Http::response(['error' => 'unavailable'], 503)
                : Http::response(['results' => [$this->ack('fan-b')]]),
            fn (Request $request) => Http::response(['result' => str_contains($request->url(), '/AABB/') ? -1 : 0]),
        );
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertRecipients(['z2:AABB', 'z2:CCDD', 'wl35:fan-a', 'wl35:fan-b']);
        $this->assertSame('failed', $schedule->fresh()->status);
        $this->assertStringContainsString('z2:AABB', $schedule->fresh()->last_error);
        $this->assertStringContainsString('wl35:fan-a', $schedule->fresh()->last_error);
    }

    public function test_transport_exception_continues_to_later_42_and_35_recipients(): void
    {
        $group = Group::factory()->create();
        $this->members($group, 'AABB', 'fan-a');
        $this->members($group, 'CCDD', 'fan-b');
        $schedule = $this->schedule(['target_scope' => 'group', 'group_id' => $group->id]);
        $cloud = $this->mock(Z2DeviceService::class);
        $cloud->shouldReceive('powerOff')->once()->with('AABB')->andThrow(new \RuntimeException('Private response details'));
        $cloud->shouldReceive('powerOff')->once()->with('CCDD')->andReturnTrue();
        $this->fakeCommands();
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertRecipients(['wl35:fan-a', 'wl35:fan-b']);
        $this->assertSame('failed', $schedule->fresh()->status);
        $this->assertStringNotContainsString('Private response details', $schedule->fresh()->last_error);
    }

    public function test_format_uses_long_timeout_and_rejects_contradictory_gateway_summary(): void
    {
        $group = Group::factory()->create();
        foreach (['fan-a', 'fan-b'] as $id) {
            Wl35DeviceProfile::create(['device_id' => $id, 'name' => $id, 'group_id' => $group->id]);
            Wl35DeviceMedia::create(['device_id' => $id, 'video_index' => 1]);
        }
        $schedule = $this->schedule(['type' => 'format_sd', 'target_scope' => 'group', 'group_id' => $group->id]);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('sendCommand')->once()->with(['command' => 'format_sd', 'targets' => ['wl35:fan-a']], true)
            ->andReturn(['success' => false, 'results' => [$this->ack('fan-a')]]);
        $fleet->shouldReceive('sendCommand')->once()->with(['command' => 'format_sd', 'targets' => ['wl35:fan-b']], true)
            ->andReturn(['command' => 'power', 'results' => [$this->ack('fan-b')]]);
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('failed', $schedule->fresh()->status);
        $this->assertSame(2, Wl35DeviceMedia::count());
        Http::assertNothingSent();
    }
}
