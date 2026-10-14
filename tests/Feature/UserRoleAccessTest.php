<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\Group;
use App\Models\User;
use App\Services\UserService;
use App\Services\Z2\Z2CampaignSyncService;
use App\Services\Z2\Z2GroupService;
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
        return [['superadmin', true, true, true], ['admin', true, false, false],
            ['tecnico', false, false, true], ['campanas', true, false, true]];
    }

    public static function landingRoles(): array
    {
        return [['superadmin', 'dashboard.index'], ['admin', 'dashboard.index'],
            ['campanas', 'dashboard.index'], ['tecnico', 'establishments.index']];
    }

    #[DataProvider('landingRoles')]
    public function test_login_and_authenticated_guest_redirect_to_an_accessible_landing(string $role, string $landing): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route($landing));
        $this->assertAuthenticatedAs($user);
        $this->get('/login')->assertRedirect(route($landing));
        Http::assertNothingSent();
    }

    #[DataProvider('roles')]
    public function test_campaign_user_and_device_modules_enforce_roles_on_web_and_api(string $role, bool $operations, bool $users, bool $devices): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get('/campaigns')->assertStatus($operations ? 200 : 403);
        $this->getJson('/api/campaigns')->assertStatus($operations ? 200 : 403);
        $this->get('/users')->assertStatus($users ? 200 : 403);
        $this->get('/users/create')->assertStatus($users ? 200 : 403);
        $this->get('/devices/create')->assertStatus($users ? 200 : 403);
        $device = Device::factory()->create(['mac_address' => '']);
        $this->getJson('/api/devices/'.$device->id)->assertStatus($devices ? 200 : 403);
        Http::assertNothingSent();
    }

    public function test_admin_cannot_access_or_manage_any_user_account(): void
    {
        $admin = User::factory()->admin()->create();
        $superadmin = User::factory()->superadmin()->create(['name' => 'Cuenta reservada']);
        $otherAdmin = User::factory()->admin()->create(['name' => 'Administración reservada']);
        $technician = User::factory()->tecnico()->create(['name' => 'Instalador permitido']);
        $this->actingAs($admin);

        $this->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();
        foreach ([$superadmin, $otherAdmin, $admin, $technician] as $protected) {
            $this->get('/users/'.$protected->id.'/edit')->assertForbidden();
            $this->put('/users/'.$protected->id, ['name' => 'Intrusión'])->assertForbidden();
            $this->delete('/users/'.$protected->id)->assertForbidden();
        }

        $this->post('/users', $this->newUser('tecnico'))->assertForbidden();
        $this->assertFalse(User::where('email', 'nuevo@example.test')->exists());
        $this->assertSame('Instalador permitido', $technician->fresh()->name);
        $this->assertTrue(Hash::check('password', $technician->fresh()->password));
    }

    public function test_superadmin_cannot_assign_unknown_or_legacy_roles(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
        $technician = User::factory()->tecnico()->create();
        foreach (['operator', 'owner'] as $role) {
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
        $this->get('/users/'.$technician->id)->assertForbidden();
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
        $this->get('/groups/'.$group->id)->assertForbidden();
        $this->get('/audit')->assertForbidden();
        $this->getJson('/api/audit')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_only_superadmin_can_send_device_or_fleet_commands(): void
    {
        $device = Device::factory()->create();
        foreach (['admin', 'tecnico', 'campanas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['power-on', 'power-off', 'bluetooth-on', 'bluetooth-off', 'disable', 'enable',
                'change-group', 'assign-content', 'unbind', 'assign-media', 'remove-media', 'format-sd', 'set-volume'] as $action) {
                $this->postJson('/devices/'.$device->id.'/'.$action, [])->assertForbidden();
            }
            $this->get('/devices/'.$device->id.'/edit')->assertForbidden();
            $this->putJson('/api/devices/'.$device->id, ['name' => 'Intrusión'])->assertForbidden();
            $this->deleteJson('/api/devices/'.$device->id)->assertForbidden();
            $this->postJson('/api/devices', [])->assertForbidden();
            foreach (['bulk-operation', 'bulk-format-sd', 'bulk-assign-media'] as $action) {
                $this->postJson('/devices/'.$action, [])->assertForbidden();
            }
            $this->postJson('/api/devices/'.$device->id.'/volume', [])->assertForbidden();
            $this->postJson('/api/devices/'.$device->id.'/format-sd')->assertForbidden();
            $this->postJson('/api/devices/bulk-format-sd')->assertForbidden();
            $this->postJson('/fleet/commands', [])->assertForbidden();
            $this->postJson('/fleet/uploads', [])->assertForbidden();
            $this->patchJson('/devices/wl35/known', ['name' => 'Intrusión'])->assertForbidden();
            $this->deleteJson('/devices/wl35/known/videos', [])->assertForbidden();
            $this->postJson('/devices/wl35/known/volume', [])->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_administrator_and_campaigns_cannot_access_removed_modules(): void
    {
        foreach (['admin', 'campanas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['users', 'audit', 'notifications', 'analytics', 'establishments'] as $module) {
                $this->getJson('/'.$module)->assertForbidden();
            }
            foreach (['audit', 'notifications', 'analytics'] as $module) {
                $this->getJson('/api/'.$module)->assertForbidden();
            }
        }
        Http::assertNothingSent();
    }

    public function test_technician_only_reaches_establishments_and_device_information(): void
    {
        $this->actingAs(User::factory()->tecnico()->create());
        $this->get('/establishments')->assertOk();
        $this->get('/establishments/create')->assertOk();
        $this->getJson('/business-types')->assertOk();
        foreach (['dashboard', 'campaigns', 'media', 'instant-play', 'groups', 'schedules',
            'users', 'notifications', 'audit', 'analytics'] as $module) {
            $this->get('/'.$module)->assertForbidden();
        }
        foreach (['dashboard', 'campaigns', 'media', 'groups', 'schedules', 'notifications', 'audit', 'analytics'] as $module) {
            $this->getJson('/api/'.$module)->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_campaign_role_can_create_groups_but_cannot_read_manage_or_command_them(): void
    {
        $group = Group::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'campanas']));
        $this->get('/groups/create')->assertOk()->assertDontSee('href="'.route('groups.index').'"', false);
        $this->mock(Z2GroupService::class)->shouldReceive('createGroup')->twice()->with('Nuevo grupo')->andReturn($group);
        $response = $this->post('/groups', ['name' => 'Nuevo grupo'])->assertRedirect()->assertSessionHas('success');
        $this->assertNotSame(route('groups.index'), $response->headers->get('Location'));
        $this->get($response->headers->get('Location'))->assertOk();
        $this->postJson('/api/groups', ['name' => 'Nuevo grupo'])->assertCreated()->assertJsonPath('id', $group->id);
        foreach (['/groups', '/groups/'.$group->id, '/groups/'.$group->id.'/edit',
            '/api/groups', '/api/groups/'.$group->id] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        foreach (['/groups', '/api/groups'] as $url) {
            $this->putJson($url.'/'.$group->id, ['name' => 'Intrusión'])->assertForbidden();
            $this->deleteJson($url.'/'.$group->id)->assertForbidden();
        }
        foreach (['power-on', 'power-off', 'change-content', 'publish-campaign'] as $action) {
            $this->postJson('/groups/'.$group->id.'/'.$action, [])->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_admin_and_campaign_roles_can_publish_from_the_campaign_module(): void
    {
        $campaign = Campaign::factory()->create();
        $this->mock(Z2CampaignSyncService::class)->shouldReceive('activate')->twice()
            ->withArgs(fn (Campaign $target): bool => $target->is($campaign))->andReturnTrue();
        foreach (['admin', 'campanas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post('/campaigns/'.$campaign->id.'/activate')->assertRedirect()->assertSessionHas('success');
        }
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
