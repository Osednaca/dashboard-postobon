<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRoleMigrationTest extends TestCase
{
    // SQLite must be able to suspend FK checks while rebuilding an enum column.
    // A surrounding test transaction prevents that PRAGMA from taking effect.
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        // This test explicitly exercises its own down migration. Avoid rolling back
        // every historical migration, and discard this in-memory database instead.
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_legacy_accounts_and_passwords_survive_migration_and_rollback(): void
    {
        // Start from the previous role schema; never touch a configured production database.
        User::count();
        $migration = require database_path('migrations/2026_10_14_000001_expand_user_roles.php');
        $migration->down();
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);
        $campaign = Campaign::factory()->create(['created_by' => $admin->id]);
        $password = $admin->password;
        $migration->up();

        $this->assertSame('superadmin', $admin->fresh()->role);
        $this->assertSame('tecnico', $operator->fresh()->role);
        $this->assertSame($password, $admin->fresh()->password);
        $this->assertSame($admin->id, $campaign->fresh()->created_by);
        $this->assertTrue(Hash::check('password', $operator->fresh()->password));
        $newAdmin = User::factory()->admin()->create();
        $migration->down();

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame('operator', $operator->fresh()->role);
        $this->assertSame('admin', $newAdmin->fresh()->role);
        $this->assertSame($admin->id, $campaign->fresh()->created_by);
        $this->assertSame(3, User::count());
        $migration->up();
    }

    public function test_mysql_sql_expands_enum_before_mapping_and_restricting_without_connecting(): void
    {
        config(['database.connections.role_sql' => [
            'driver' => 'mysql', 'host' => 'unused.invalid', 'database' => 'unused',
            'username' => '', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
        ]]);
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection('role_sql');
        $connection = DB::connection('role_sql');
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('getAttribute')->willReturn('8.0.36');
        $pdo->expects($this->never())->method('prepare');
        $pdo->expects($this->never())->method('exec');
        $pdo->expects($this->never())->method('query');
        $connection->setPdo($pdo);
        $connection->setReadPdo($pdo);

        try {
            $migration = require database_path('migrations/2026_10_14_000001_expand_user_roles.php');
            $queries = $connection->pretend(fn () => $migration->up());
            $this->assertCount(4, $queries);
            $this->assertStringContainsString("enum('admin', 'operator', 'superadmin', 'tecnico')", $queries[0]['query']);
            $this->assertSame(['superadmin', 'admin'], $queries[1]['bindings']);
            $this->assertSame(['tecnico', 'operator'], $queries[2]['bindings']);
            $this->assertStringContainsString("enum('superadmin', 'admin', 'tecnico')", $queries[3]['query']);
            $rollback = $connection->pretend(fn () => $migration->down());
            $this->assertCount(4, $rollback);
            $this->assertSame(['admin', 'superadmin'], $rollback[1]['bindings']);
            $this->assertSame(['operator', 'tecnico'], $rollback[2]['bindings']);
            $this->assertStringContainsString("enum('admin', 'operator')", $rollback[3]['query']);
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('role_sql');
        }
    }
}
