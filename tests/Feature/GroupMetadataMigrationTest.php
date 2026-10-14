<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Device;
use App\Models\Group;
use App\Models\Wl35DeviceProfile;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GroupMetadataMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_group_and_memberships_survive_metadata_migration_and_rollback(): void
    {
        $migration = require database_path('migrations/2026_10_14_000003_add_group_segmentation_metadata.php');
        $migration->down();
        $group = Group::factory()->create(['name' => 'Medellín', 'description' => 'Conservar']);
        $device = Device::factory()->create(['group_id' => $group->id]);
        $profile = Wl35DeviceProfile::create(['device_id' => 'migration-35', 'name' => '35', 'group_id' => $group->id]);
        $migration->up();
        $this->assertNull($group->fresh()->business_type_id);
        $type = BusinessType::create(['name' => 'Oxxo']);
        $group->update(['business_type_id' => $type->id, 'city' => 'Medellín', 'latitude' => 6.24, 'longitude' => -75.58]);
        $type->delete();
        $this->assertNull($group->fresh()->business_type_id);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('groups', 'city'));
        $this->assertSame('Conservar', $group->fresh()->description);
        $this->assertSame($group->id, $device->fresh()->group_id);
        $this->assertSame($group->id, $profile->fresh()->group_id);
        $migration->up();
    }
}
