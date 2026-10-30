<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CampaignDevicePlayback;
use App\Models\Device;
use App\Models\Group;
use App\Models\Media;
use App\Models\User;
use App\Models\Wl35DeviceMedia;
use App\Models\Wl35DeviceProfile;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeviceBulkVideoRemovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatecloud.base_url' => 'http://cloud.test', 'unifiedfleet.base_url' => 'http://gateway.test',
            'unifiedfleet.fallback_base_url' => '', 'unifiedfleet.token' => 'test', 'cache.default' => 'array']);
        Http::preventStrayRequests();
        Storage::fake('public');
        $this->actingAs(User::factory()->superadmin()->create());
    }

    private function media(string $path = 'campaign.mp4'): Media
    {
        return Media::factory()->video()->create(['file_path' => $path]);
    }

    private function wl35(string $id, Media $media, int $index, ?int $groupId = null): void
    {
        Wl35DeviceProfile::create(['device_id' => $id, 'name' => $id, 'group_id' => $groupId]);
        Wl35DeviceMedia::create(['device_id' => $id, 'media_id' => $media->id, 'video_index' => $index]);
    }

    private function fakeGateway(array $responses = []): void
    {
        Http::fake($responses + [
            'gateway.test/api/fleet' => Http::response(['devices' => array_map(fn ($id) => [
                'id' => $id, 'type' => 'wl35', 'session_ready' => true, 'video_count' => 5,
            ], ['fan-a', 'fan-b'])]),
            'gateway.test/api/health' => Http::response(['ok' => true]),
        ]);
    }

    private function remove(Media $media, array $keys, ?int $groupId = null)
    {
        return $this->postJson(route('devices.bulk-remove-video'), [
            'media_id' => $media->id, 'device_keys' => $keys, 'group_id' => $groupId,
        ]);
    }

    public function test_same_library_video_uses_each_35_index_and_preserves_library_and_other_devices(): void
    {
        $media = $this->media();
        $group = Group::factory()->create();
        $this->wl35('fan-a', $media, 1, $group->id);
        $this->wl35('fan-b', $media, 3, $group->id);
        $this->wl35('unselected', $media, 2);
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'filename' => 'other.mp4', 'video_index' => 2]);
        $this->fakeGateway([
            'gateway.test/api/devices/fan-a/video/1' => Http::response(['success' => true, 'index' => 1]),
            'gateway.test/api/devices/fan-b/video/3' => Http::response(['success' => true, 'index' => 3]),
        ]);

        $this->remove($media, ['wl35:fan-a', 'wl35:fan-b'], $group->id)->assertOk()
            ->assertJsonPath('results.0.status', 'accepted')->assertJsonPath('results.1.status', 'accepted');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        $this->assertDatabaseMissing('wl35_device_media', ['device_id' => 'fan-a', 'media_id' => $media->id]);
        $this->assertDatabaseMissing('wl35_device_media', ['device_id' => 'fan-b', 'media_id' => $media->id]);
        $this->assertDatabaseHas('wl35_device_media', ['device_id' => 'fan-a', 'filename' => 'other.mp4', 'video_index' => 1]);
        $this->assertDatabaseHas('wl35_device_media', ['device_id' => 'unselected', 'media_id' => $media->id, 'video_index' => 2]);
        $this->assertSame(2, AuditLog::where('action', 'remove_video')->where('user_id', auth()->id())->count());
        Http::assertSentCount(4);
    }

    public function test_wl35_bulk_removal_cannot_bypass_a_durable_campaign_session(): void
    {
        $media = $this->media();
        $this->wl35('fan-a', $media, 1);
        CampaignDevicePlayback::create(['target_key' => 'wl35:fan-a', 'baseline' => ['type' => 'wl35', 'count' => 1, 'selection' => 1], 'restore_state' => 'managed']);
        $this->remove($media, ['wl35:fan-a'])->assertOk()->assertJsonPath('results.0.status', 'failed');
        $this->assertSame(1, Wl35DeviceMedia::sole()->video_index);
        Http::assertNothingSent();
    }

    public function test_mixed_selection_continues_after_failure_and_never_uploads_or_deletes_library(): void
    {
        $media = $this->media('http://cloud.test/fileDownload/Videos/campaign.mp4');
        $device = Device::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);
        $this->wl35('fan-a', $media, 2);
        $this->fakeGateway([
            'gateway.test/api/devices/fan-a/video/2' => Http::response(['error' => 'secret provider detail'], 500),
            'cloud.test/api/devices/AABBCCDDEEFF/remove-media' => Http::response([
                'result' => 0, 'removed' => 'campaign.mp4', 'remainingPlaylist' => ['other.mp4'],
            ]),
            'cloud.test/api/devices/AABBCCDDEEFF' => Http::response(['result' => 0, 'device' => ['playlist' => ['campaign.mp4']]]),
        ]);

        $this->remove($media, ['wl35:fan-a', 'z2:'.$device->id])->assertOk()
            ->assertJsonPath('results.0.status', 'failed')->assertJsonPath('results.1.status', 'accepted')
            ->assertDontSee('secret provider detail');
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/remove-media') && $request['filename'] === 'campaign.mp4');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/uploads') || str_contains($request->url(), '/api/media'));
        $this->assertDatabaseHas('wl35_device_media', ['device_id' => 'fan-a', 'media_id' => null, 'video_index' => 2]);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        $this->assertSame(['failed', 'accepted'], AuditLog::orderBy('id')->get()->pluck('details.status')->all());
    }

    public function test_all_targets_are_validated_before_any_dispatch_even_when_invalid_target_is_last(): void
    {
        $media = $this->media();
        $group = Group::factory()->create();
        $device = Device::factory()->create(['group_id' => $group->id]);
        $this->wl35('fan-a', $media, 1);
        foreach (['wl35:fan-a', 'wl35:unknown', 'z2:999999'] as $invalid) {
            $this->remove($media, ['z2:'.$device->id, $invalid], $group->id)->assertUnprocessable()->assertJsonValidationErrors('device_keys');
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_duplicate_physical_macs_cannot_dispatch_twice(): void
    {
        $a = Device::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);
        $b = Device::factory()->create(['mac_address' => 'aabbccddeeff']);
        $this->remove($this->media(), ['z2:'.$a->id, 'z2:'.$b->id])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public static function badAcknowledgments(): array
    {
        return [[[]], [['success' => false, 'index' => 2]], [['success' => true, 'index' => 1]], [['success' => true, 'index' => '2']]];
    }

    #[DataProvider('badAcknowledgments')]
    public function test_invalid_acknowledgment_does_not_shift_indices_and_blocks_unsafe_retry(array $body): void
    {
        $media = $this->media();
        $this->wl35('fan-a', $media, 2);
        $this->fakeGateway(['gateway.test/api/devices/fan-a/video/2' => Http::response($body)]);
        $this->remove($media, ['wl35:fan-a'])->assertOk()->assertJsonPath('results.0.status', 'failed');
        $this->assertDatabaseHas('wl35_device_media', ['device_id' => 'fan-a', 'media_id' => null, 'video_index' => 2]);
        $this->remove($media, ['wl35:fan-a'])->assertOk()->assertJsonPath('results.0.status', 'failed');
        Http::assertSentCount(2);
    }

    public function test_unknown_and_outdated_indices_never_send_delete(): void
    {
        $media = $this->media();
        $this->wl35('fan-a', $media, 6);
        Wl35DeviceProfile::create(['device_id' => 'fan-b', 'name' => 'Fan B']);
        $this->fakeGateway();
        $this->remove($media, ['wl35:fan-a', 'wl35:fan-b'])->assertOk()
            ->assertJsonPath('results.0.status', 'failed')->assertJsonPath('results.1.status', 'failed');
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_pre_dispatch_gateway_failure_preserves_mapping_for_later_retry(): void
    {
        $media = $this->media();
        $this->wl35('fan-a', $media, 2);
        $this->fakeGateway(['gateway.test/api/fleet' => Http::response(['error' => 'private endpoint'], 500)]);
        $this->remove($media, ['wl35:fan-a'])->assertOk()->assertJsonPath('results.0.status', 'failed')->assertDontSee('private endpoint');
        $this->assertDatabaseHas('wl35_device_media', ['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 2]);
        Http::assertSentCount(1);
    }

    public function test_local_42_media_is_never_uploaded_to_resolve_removal(): void
    {
        $media = $this->media('media/local.mp4');
        Storage::disk('public')->put($media->file_path, 'keep me');
        $device = Device::factory()->create();
        $this->remove($media, ['z2:'.$device->id])->assertOk()->assertJsonPath('results.0.status', 'failed');
        Http::assertNothingSent();
        Storage::disk('public')->assertExists($media->file_path);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_occupied_device_lock_rejects_overlapping_removal_without_command(): void
    {
        $media = $this->media();
        $this->wl35('fan-a', $media, 1);
        $lock = Cache::lock('device-video-removal:wl35:'.hash('sha256', 'fan-a'), 120);
        $this->assertTrue($lock->get());
        try {
            $this->remove($media, ['wl35:fan-a'])->assertOk()->assertJsonPath('results.0.status', 'failed');
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_deleted_media_groups_and_non_videos_are_rejected_before_dispatch(): void
    {
        $device = Device::factory()->create();
        $deleted = $this->media();
        $deleted->delete();
        $this->remove($deleted, ['z2:'.$device->id])->assertUnprocessable()->assertJsonValidationErrors('media_id');
        $image = Media::factory()->image()->create();
        $this->remove($image, ['z2:'.$device->id])->assertUnprocessable()->assertJsonValidationErrors('media_id');
        $group = Group::factory()->create();
        $group->delete();
        $this->remove($this->media('another.mp4'), ['z2:'.$device->id], $group->id)->assertUnprocessable()->assertJsonValidationErrors('group_id');
        Http::assertNothingSent();
    }

    public function test_non_mp4_video_can_be_removed_by_its_existing_identity(): void
    {
        $media = $this->media('campaign.mov');
        $media->update(['mime_type' => 'video/quicktime']);
        $device = Device::factory()->create();
        $this->mock(Z2DeviceService::class)->shouldReceive('removeVideoFromDevice')->once()
            ->with($device->mac_address, 'campaign.mov')->andReturn(true);
        $this->remove($media, ['z2:'.$device->id])->assertOk()->assertJsonPath('results.0.status', 'accepted');
    }

    public function test_transport_runtime_exception_is_not_exposed_to_operator(): void
    {
        $device = Device::factory()->create();
        $this->mock(Z2DeviceService::class)->shouldReceive('removeVideoFromDevice')->once()
            ->andThrow(new \RuntimeException('private provider credentials'));
        $this->remove($this->media(), ['z2:'.$device->id])->assertOk()
            ->assertJsonPath('results.0.status', 'failed')->assertDontSee('private provider credentials');
    }

    public function test_audit_failure_does_not_change_an_accepted_device_result(): void
    {
        $device = Device::factory()->create();
        $media = $this->media();
        $this->mock(Z2DeviceService::class)->shouldReceive('removeVideoFromDevice')->once()->andReturn(true);
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(fn () => throw new \RuntimeException('audit storage failure'));
        try {
            $this->remove($media, ['z2:'.$device->id])->assertOk()->assertJsonPath('results.0.status', 'accepted');
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }
    }

    public function test_mapping_failure_after_ack_is_accepted_with_warning_and_cannot_delete_wrong_video_on_retry(): void
    {
        $media = $this->media();
        $this->wl35('fan-a', $media, 1);
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'filename' => 'other.mp4', 'video_index' => 2]);
        $this->fakeGateway(['gateway.test/api/devices/fan-a/video/1' => Http::response(['success' => true, 'index' => 1])]);
        $dispatcher = Wl35DeviceMedia::getEventDispatcher();
        Wl35DeviceMedia::setEventDispatcher(clone $dispatcher);
        Wl35DeviceMedia::updating(fn () => throw new \RuntimeException('mapping storage failure'));
        try {
            $this->remove($media, ['wl35:fan-a'])->assertOk()->assertJsonPath('results.0.status', 'accepted')
                ->assertSee('No repitas la orden');
        } finally {
            Wl35DeviceMedia::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseMissing('wl35_device_media', ['device_id' => 'fan-a', 'media_id' => $media->id]);
        $this->remove($media, ['wl35:fan-a'])->assertOk()->assertJsonPath('results.0.status', 'failed');
        Http::assertSentCount(2);
    }

    public static function readonlyRoles(): array
    {
        return [['admin'], ['tecnico'], ['campanas']];
    }

    #[DataProvider('readonlyRoles')]
    public function test_other_roles_cannot_remove_content(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->remove($this->media(), ['z2:1'])->assertForbidden();
        Http::assertNothingSent();
    }
}
