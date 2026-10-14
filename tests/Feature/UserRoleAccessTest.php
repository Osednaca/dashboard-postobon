<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\Group;
use App\Models\User;
use App\Services\UserService;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserRoleAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public static function roles(): array
    {
        return [['superadmin', true], ['admin', true], ['tecnico', false]];
    }

    #[DataProvider('roles')]
    public function test_campaign_and_user_modules_enforce_roles_on_web_and_api(string $role, bool $manager): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get('/campaigns')->assertStatus($manager ? 200 : 403);
        $this->getJson('/api/campaigns')->assertStatus($manager ? 200 : 403);
        $this->get('/users')->assertStatus($manager ? 200 : 403);
        $this->get('/users/create')->assertStatus($manager ? 200 : 403);
        $this->get('/devices/create')->assertOk();
        $device = Device::factory()->create(['mac_address' => '']);
        $this->getJson('/api/devices/'.$device->id)->assertOk();
    }

    public function test_admin_lists_and_manages_only_technicians(): void
    {
        $admin = User::factory()->admin()->create();
        $superadmin = User::factory()->superadmin()->create(['name' => 'Cuenta reservada']);
        $otherAdmin = User::factory()->admin()->create(['name' => 'Administración reservada']);
        $technician = User::factory()->tecnico()->create(['name' => 'Instalador permitido']);
        $this->actingAs($admin);

        $this->get('/users')->assertOk()->assertSee('Instalador permitido')
            ->assertDontSee('Cuenta reservada')->assertDontSee('Administración reservada');
        $this->get('/users/create')->assertOk()->assertSee('value="tecnico"', false)
            ->assertDontSee('value="superadmin"', false)->assertDontSee('value="admin"', false);
        foreach ([$superadmin, $otherAdmin, $admin] as $protected) {
            $this->get('/users/'.$protected->id.'/edit')->assertForbidden();
            $this->put('/users/'.$protected->id, ['name' => 'Intrusión'])->assertForbidden();
            $this->delete('/users/'.$protected->id)->assertForbidden();
        }

        $this->post('/users', $this->newUser('tecnico'))->assertRedirect(route('users.index'));
        $created = User::where('email', 'nuevo@example.test')->firstOrFail();
        $this->assertSame('tecnico', $created->role);
        $this->put('/users/'.$technician->id, ['name' => 'Técnico actualizado', 'role' => 'tecnico', 'password' => ''])
            ->assertRedirect(route('users.index'));
        $this->assertSame('Técnico actualizado', $technician->fresh()->name);
        $this->assertTrue(Hash::check('password', $technician->fresh()->password));
        $this->delete('/users/'.$created->id)->assertRedirect(route('users.index'));
        $this->assertModelMissing($created);
    }

    public function test_admin_cannot_assign_privileged_or_legacy_roles(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $technician = User::factory()->tecnico()->create();
        foreach (['superadmin', 'admin', 'operator', 'owner'] as $role) {
            $this->post('/users', $this->newUser($role))->assertSessionHasErrors('role');
            $this->put('/users/'.$technician->id, ['role' => $role])->assertSessionHasErrors('role');
        }
        $this->assertSame('tecnico', $technician->fresh()->role);
        $this->assertFalse(User::where('email', 'nuevo@example.test')->exists());
    }

    public function test_superadmin_can_assign_every_new_role_and_manage_another_admin(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($superadmin);
        foreach (array_keys(User::ROLES) as $role) {
            $this->post('/users', $this->newUser($role, $role.'@example.test'))
                ->assertRedirect(route('users.index'));
            $this->assertSame($role, User::where('email', $role.'@example.test')->firstOrFail()->role);
        }
        $this->put('/users/'.$admin->id, ['role' => 'superadmin'])->assertRedirect(route('users.index'));
        $this->assertSame('superadmin', $admin->fresh()->role);
        $this->delete('/users/'.$admin->id)->assertRedirect(route('users.index'));
        $this->assertModelMissing($admin);
    }

    public function test_last_superadmin_and_self_deletion_are_protected(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin);
        $this->put('/users/'.$superadmin->id, ['role' => 'tecnico'])->assertSessionHasErrors('role');
        $this->delete('/users/'.$superadmin->id)->assertForbidden();
        $this->assertSame('superadmin', $superadmin->fresh()->role);

        User::factory()->superadmin()->create();
        $this->delete('/users/'.$superadmin->id)->assertForbidden();
        $this->put('/users/'.$superadmin->id, ['role' => 'admin'])->assertRedirect(route('users.index'));
        $this->assertSame('admin', $superadmin->fresh()->role);
    }

    public function test_service_cannot_bypass_the_last_superadmin_guard(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $service = app(UserService::class);
        foreach (['assignRole', 'delete'] as $action) {
            try {
                $action === 'assignRole' ? $service->assignRole($superadmin->id, 'tecnico') : $service->delete($superadmin->id);
                $this->fail('El último superadministrador debe conservarse.');
            } catch (ValidationException) {
                $this->assertSame('superadmin', $superadmin->fresh()->role);
            }
        }
    }

    public function test_technician_profile_is_read_only_and_other_accounts_are_hidden(): void
    {
        $technician = User::factory()->tecnico()->create();
        $other = User::factory()->tecnico()->create();
        $this->actingAs($technician);
        $this->get('/users/'.$technician->id)->assertOk()->assertSee('Técnico (instalador)')
            ->assertDontSee(route('users.edit', $technician), false)
            ->assertDontSee('href="'.route('users.index').'"', false)->assertDontSee(route('campaigns.index'), false);
        $this->get('/users/'.$other->id)->assertForbidden();
        $this->put('/users/'.$technician->id, ['role' => 'superadmin'])->assertForbidden();
        $this->post('/users', $this->newUser('tecnico'))->assertForbidden();
    }

    public function test_technician_cannot_publish_campaigns_through_device_group_or_playback_endpoints(): void
    {
        $technician = User::factory()->tecnico()->create();
        $device = Device::factory()->create();
        $group = Group::factory()->create();
        $campaign = Campaign::factory()->create();
        $this->actingAs($technician);
        foreach ([
            '/devices/'.$device->id.'/assign-content',
            '/groups/'.$group->id.'/change-content',
            '/groups/'.$group->id.'/publish-campaign',
            '/instant-play/play-campaign',
            '/campaigns/'.$campaign->id.'/activate',
        ] as $path) {
            $this->post($path, ['campaign_id' => $campaign->id])->assertForbidden();
        }
        $this->postJson('/api/campaigns', [])->assertForbidden();
        $this->putJson('/api/campaigns/'.$campaign->id, ['name' => 'Intrusión'])->assertForbidden();
        $this->deleteJson('/api/campaigns/'.$campaign->id)->assertForbidden();
        $this->get('/groups/'.$group->id)->assertOk()->assertDontSee('Publicar Campaña');
        $this->get('/audit')->assertForbidden();
        $this->getJson('/api/audit')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_technician_can_control_devices_with_a_fake_cloud(): void
    {
        $device = Device::factory()->create();
        $this->mock(Z2DeviceService::class, function ($mock) use ($device) {
            $mock->shouldReceive('powerOn')->once()->with($device->mac_address)->andReturn(true);
        });
        $this->actingAs(User::factory()->tecnico()->create())
            ->postJson('/devices/'.$device->id.'/power-on')->assertStatus(202)->assertJsonPath('success', true);
        Http::assertNothingSent();
    }

    public function test_unknown_role_cannot_reach_authenticated_fleet_or_api_routes(): void
    {
        $this->actingAs(User::factory()->make(['id' => 900, 'role' => 'operator']));
        $this->postJson('/fleet/commands', ['command' => 'power'])->assertForbidden();
        $this->postJson('/fleet/uploads', [])->assertForbidden();
        $this->getJson('/api/devices')->assertForbidden();
        Http::assertNothingSent();
    }

    private function newUser(string $role, string $email = 'nuevo@example.test'): array
    {
        return ['name' => 'Nueva cuenta', 'email' => $email, 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => $role];
    }
}
