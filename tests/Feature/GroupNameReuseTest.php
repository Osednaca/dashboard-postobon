<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroupNameReuseTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_deleted_group_name_can_be_reused_without_losing_history(): void
    {
        $old = Group::factory()->create(['name' => 'Zona Centro', 'z2_group_id' => null]);

        $this->delete('/groups/'.$old->id)->assertRedirect('/groups');
        $this->assertSoftDeleted($old);

        $this->post('/groups', ['name' => 'Zona Centro'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $new = Group::where('name', 'Zona Centro')->firstOrFail();
        $this->assertNotEquals($old->id, $new->id);
        $this->assertSame(2, Group::withTrashed()->where('name', 'Zona Centro')->count());

        $this->postJson('/api/groups', ['name' => 'Zona Centro'])->assertUnprocessable()->assertJsonValidationErrors('name');
        Http::assertNothingSent();
    }

    public function test_editing_a_group_checks_only_other_active_group_names(): void
    {
        $removed = Group::factory()->create(['name' => 'Nombre anterior']);
        $removed->delete();
        $group = Group::factory()->create(['name' => 'Actual']);
        $other = Group::factory()->create(['name' => 'Ocupado']);

        $this->putJson('/api/groups/'.$group->id, ['name' => 'Nombre anterior'])->assertOk();
        $this->putJson('/api/groups/'.$group->id, ['name' => 'Nombre anterior'])->assertOk();
        $this->putJson('/api/groups/'.$group->id, ['name' => $other->name])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame('Nombre anterior', $group->fresh()->name);
    }

    public function test_database_rejects_duplicate_active_names_even_without_validation(): void
    {
        $deleted = Group::factory()->create(['name' => 'Protegido']);
        $deleted->delete();
        Group::factory()->create(['name' => 'Protegido']);

        $this->expectException(QueryException::class);
        Group::factory()->create(['name' => 'Protegido']);
    }

    public function test_rollback_refuses_to_discard_historical_duplicates(): void
    {
        $old = Group::factory()->create(['name' => 'Histórico']);
        $old->delete();
        Group::factory()->create(['name' => 'Histórico']);
        $migration = require database_path('migrations/2026_10_28_000004_allow_reuse_of_deleted_group_names.php');

        try {
            $migration->down();
            $this->fail('Rollback should refuse duplicate historical names.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('duplicate historical names', $exception->getMessage());
        }

        $this->assertSame(2, Group::withTrashed()->where('name', 'Histórico')->count());
    }

    public function test_rollback_and_reapply_are_possible_without_duplicate_names(): void
    {
        Group::factory()->create(['name' => 'Único']);
        $migration = require database_path('migrations/2026_10_28_000004_allow_reuse_of_deleted_group_names.php');
        $migration->down();
        $migration->up();

        $group = Group::where('name', 'Único')->firstOrFail();
        $group->delete();
        $this->postJson('/api/groups', ['name' => 'Único'])->assertCreated();
    }
}
