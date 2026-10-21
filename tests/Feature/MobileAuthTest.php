<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MobileApi;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_issues_expiring_scoped_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create(['role' => 'tecnico']);
        $response = $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'iPhone',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer')->assertJsonMissingPath('data.user.password');
        $plainToken = $response->json('data.token');
        $token = PersonalAccessToken::findToken($plainToken);
        $this->assertSame(MobileApi::ABILITIES, $token->abilities);
        $this->assertTrue($token->expires_at->isFuture());
        $this->withToken($plainToken)->getJson('/api/mobile/v1/auth/me')->assertOk()->assertJsonPath('data.role', 'tecnico');
        $this->postJson('/api/mobile/v1/auth/logout')->assertNoContent();
        $this->assertModelMissing($token);
        Auth::forgetGuards();
        $this->getJson('/api/mobile/v1/auth/me')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_bad_credentials_and_disallowed_roles_cannot_login(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->postJson('/api/mobile/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized()->assertJsonPath('error.code', 'invalid_credentials');
        $this->postJson('/api/mobile/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_mobile_superadmin_token_cannot_use_legacy_api_even_public_routes(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);
        $token = $user->createToken('mobile:test', MobileApi::ABILITIES, now()->addDay())->plainTextToken;
        $this->withToken($token)->getJson('/api/user')->assertForbidden()->assertJsonPath('error.code', 'mobile_token_restricted');
        $this->postJson('/api/devices/1/format-sd')->assertForbidden();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertForbidden();
    }

    public function test_session_and_wildcard_legacy_tokens_do_not_grant_mobile_access(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);
        $token = $user->createToken('legacy', ['*'])->plainTextToken;
        $this->withToken($token)->getJson('/api/mobile/v1/auth/me')->assertForbidden();
        Auth::forgetGuards();
        $this->actingAs($user)->withHeader('Authorization', '')->getJson('/api/mobile/v1/auth/me')->assertForbidden();
    }

    public function test_expired_token_and_changed_role_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'tecnico']);
        $expired = $user->createToken('mobile:expired', MobileApi::ABILITIES, now()->subMinute())->plainTextToken;
        $this->withToken($expired)->getJson('/api/mobile/v1/auth/me')->assertUnauthorized();
        Auth::forgetGuards();
        $token = $user->createToken('mobile:test', MobileApi::ABILITIES, now()->addDay())->plainTextToken;
        $user->update(['role' => 'campanas']);
        $this->withToken($token)->getJson('/api/mobile/v1/auth/me')->assertForbidden();
    }

    public function test_existing_token_table_survives_migration_and_rollback(): void
    {
        $user = User::factory()->create(['role' => 'tecnico']);
        $token = $user->createToken('mobile:test', MobileApi::ABILITIES)->accessToken;
        $migration = require database_path('migrations/2026_10_21_000001_create_personal_access_tokens_table.php');
        $migration->up();
        $migration->down();
        $this->assertModelExists($token);
    }

    public function test_validation_rate_limits_and_password_recovery_use_safe_json(): void
    {
        Notification::fake();
        $this->postJson('/api/mobile/v1/auth/login', [])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['code', 'message', 'fields', 'request_id']]);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/mobile/v1/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/mobile/v1/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])
            ->assertStatus(429)->assertJsonPath('error.code', 'rate_limited')->assertHeader('Retry-After');
        $this->postJson('/api/mobile/v1/auth/forgot-password', ['email' => 'missing@example.com'])->assertStatus(202);
    }
}
