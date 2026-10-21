<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Establishment;
use App\Models\User;
use App\Support\MobileApi;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MobileBusinessTypeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'tecnico']);
        $this->withToken($user->createToken('mobile:test', MobileApi::ABILITIES, now()->addDay())->plainTextToken);
    }

    public function test_catalog_crud_unique_name_and_linked_delete_rules(): void
    {
        $response = $this->postJson('/api/mobile/v1/business-types', ['name' => 'Tienda'])->assertCreated();
        $id = $response->json('data.id');
        $this->postJson('/api/mobile/v1/business-types', ['name' => 'Tienda'])->assertUnprocessable();
        $this->patchJson('/api/mobile/v1/business-types/'.$id, ['name' => 'Tienda'])->assertOk();
        $this->patchJson('/api/mobile/v1/business-types/'.$id, ['name' => 'Minimercado'])->assertOk();
        $this->getJson('/api/mobile/v1/business-types?search=Minimercado')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.establishments_count', 0);
        $establishment = Establishment::create(['name' => 'La Esquina', 'address' => 'Calle 1', 'business_type_id' => $id]);
        $this->deleteJson('/api/mobile/v1/business-types/'.$id)->assertStatus(409)
            ->assertJsonPath('error.code', 'business_type_has_establishments');
        $establishment->delete();
        $this->deleteJson('/api/mobile/v1/business-types/'.$id)->assertNoContent();
        $this->assertNull(BusinessType::find($id));
    }
}
