<?php

namespace Tests\Feature;

use App\Jobs\DistributeFleetVideoJob;
use App\Models\FleetUpload;
use App\Models\User;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FleetUploadProgressTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.token' => 'test-token']);
        Http::preventStrayRequests();
    }

    private function upload(User $user): FleetUpload
    {
        return FleetUpload::create([
            'user_id' => $user->id, 'original_name' => 'sample.mp4', 'file_path' => 'fleet-uploads/sample.mp4',
            'targets' => ['wl35:test'], 'status' => 'processing', 'phase' => 'distributing', 'progress' => 35,
            'result' => ['gateway_upload_id' => 'gateway-job', 'gateway_base_url' => 'http://gateway.test'],
        ]);
    }

    public function test_devices_page_renders_the_upload_progress_interface(): void
    {
        Http::fake(['*' => Http::response(['devices' => [], 'media' => []])]);

        $this->actingAs(User::factory()->admin()->create())->get('/devices')
            ->assertOk()->assertSee('Transmitiendo al ventilador')->assertSee('Convirtiendo el video');
    }

    public function test_devices_upload_status_uses_its_correlated_gateway_job_and_enforces_ownership(): void
    {
        $user = User::factory()->admin()->create();
        $upload = $this->upload($user);
        Http::fake(['gateway.test/api/fleet/uploads/gateway-job' => Http::response([
            'phase' => 'relaying', 'progress' => 62,
            'transfers' => [['key' => 'wl35:test', 'bytes' => 4000000, 'total_bytes' => 8000000]],
        ])]);
        $this->actingAs($user)->getJson(route('fleet.upload.status', $upload))
            ->assertOk()->assertJsonPath('phase', 'relaying')->assertJsonPath('progress', 62)
            ->assertJsonPath('transfers.0.bytes', 4000000)->assertJsonPath('result', null);
        Http::assertSentCount(1);
        $this->actingAs(User::factory()->create())->getJson(route('fleet.upload.status', $upload))->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_missing_gateway_progress_keeps_the_background_job_running(): void
    {
        $user = User::factory()->admin()->create();
        $upload = $this->upload($user);
        Http::fake(['gateway.test/*' => Http::response([], 404)]);
        $this->actingAs($user)->getJson(route('fleet.upload.status', $upload))
            ->assertOk()->assertJsonPath('status', 'processing')->assertJsonPath('progress', 35);
        $this->assertSame('processing', $upload->fresh()->status);
    }

    public function test_completed_job_preserves_each_device_error_for_the_modal(): void
    {
        $user = User::factory()->admin()->create();
        $upload = $this->upload($user);
        $upload->update([
            'status' => 'completed', 'phase' => 'completed', 'progress' => 100,
            'result' => ['total' => 1, 'succeeded' => 0, 'failed' => 1, 'results' => [
                ['key' => 'wl35:test', 'id' => 'test', 'success' => false, 'error' => 'timeout waiting for WL35 upload ACK'],
            ]],
        ]);
        Http::fake();
        $this->actingAs($user)->getJson(route('fleet.upload.status', $upload))
            ->assertOk()->assertJsonPath('result.failed', 1)
            ->assertJsonPath('result.results.0.error', 'timeout waiting for WL35 upload ACK');
        Http::assertNothingSent();
    }

    public function test_job_persists_gateway_correlation_before_starting_distribution(): void
    {
        Storage::fake('local');
        $upload = $this->upload(User::factory()->create());
        Storage::disk('local')->put($upload->file_path, 'video-fixture');
        Http::fake(function (Request $request) use ($upload) {
            if (str_ends_with($request->url(), '/api/health')) {
                return Http::response(['ok' => true]);
            }
            if (str_ends_with($request->url(), '/api/fleet/uploads')) {
                return Http::response(['upload_id' => 'new-gateway-job'], 201);
            }
            if (str_ends_with($request->url(), '/new-gateway-job/distribute')) {
                $this->assertSame('new-gateway-job', $upload->fresh()->result['gateway_upload_id']);
                $this->assertSame(['wl35:test'], $request['targets']);

                return Http::response(['success' => true, 'succeeded' => 1, 'failed' => 0]);
            }
            $this->fail('Unexpected HTTP request: '.$request->url());
        });
        (new DistributeFleetVideoJob($upload->id))->handle(app(UnifiedFleetClient::class));
        $this->assertSame('completed', $upload->fresh()->status);
        Storage::disk('local')->assertMissing($upload->file_path);
    }
}
