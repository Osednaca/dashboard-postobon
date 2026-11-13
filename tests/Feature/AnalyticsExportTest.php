<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignStatistic;
use App\Models\Device;
use App\Models\Group;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\AnalyticsReportExportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class AnalyticsExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_selector_downloads_all_real_analytics_sections_as_excel(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
        $group = Group::factory()->create(['name' => 'Grupo exportación']);
        $device = Device::factory()->online()->create(['name' => '=SUM(A1:A2)', 'group_id' => $group->id, 'city' => 'Bogotá', 'working_hours' => 12.75]);
        Wl35DeviceProfile::create(['device_id' => 'EXPORT-35', 'name' => 'Equipo 35 sin telemetría', 'city' => 'Cali']);
        $campaign = Campaign::factory()->draft()->create(['name' => 'Campaña exportable']);
        CampaignStatistic::create(['campaign_id' => $campaign->id, 'device_id' => $device->id, 'plays' => 17, 'impressions' => 83, 'duration' => 4, 'date' => '2026-11-13']);

        $this->get('/analytics')->assertOk()->assertSee('Formato de exportación')
            ->assertSee('value="pdf"', false)->assertSee('value="xlsx"', false)->assertSee(route('analytics.export'));
        $response = $this->get('/analytics/export?format=xlsx')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'analytics-export-test-');
        file_put_contents($path, $response->getContent());
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path) === true);
            $book = $zip->getFromName('xl/workbook.xml');
            foreach (['Resumen', 'Dispositivos', 'Grupos', 'Ciudades', 'Campañas'] as $section) {
                $this->assertStringContainsString($section, $book);
            }
            $devices = $zip->getFromName('xl/worksheets/sheet2.xml');
            $this->assertStringContainsString('=SUM(A1:A2)', $devices);
            $this->assertStringNotContainsString('<f>', $devices);
            $this->assertStringContainsString('Equipo 35 sin telemetría', $devices);
            $this->assertStringContainsString('Sin datos', $devices);
            $this->assertStringContainsString('<v>12.75</v>', $devices);
            $this->assertStringContainsString('Bogotá', $zip->getFromName('xl/worksheets/sheet4.xml'));
            $campaigns = $zip->getFromName('xl/worksheets/sheet5.xml');
            $this->assertStringContainsString('Campaña exportable', $campaigns);
            $this->assertStringContainsString('<v>17</v>', $campaigns);
            $this->assertStringContainsString('<v>83</v>', $campaigns);
            $zip->close();
        } finally {
            unlink($path);
        }
        Http::assertNothingSent();
    }

    public function test_pdf_download_is_valid_even_without_inventory(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
        $response = $this->get('/analytics/export?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
        Http::assertNothingSent();
    }

    public function test_export_requires_superadmin_and_an_explicit_supported_format(): void
    {
        $this->get('/analytics/export?format=pdf')->assertRedirect(route('login'));
        foreach (['admin', 'campanas', 'tecnico'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['pdf', 'xlsx'] as $format) {
                $this->get('/analytics/export?format='.$format)->assertForbidden();
            }
        }
        $this->actingAs(User::factory()->superadmin()->create());
        foreach (['', '?format=csv', '?format[]=pdf'] as $query) {
            $this->getJson('/analytics/export'.$query)->assertUnprocessable()->assertJsonValidationErrors('format');
        }
    }

    public function test_generation_failure_returns_a_useful_error_instead_of_a_broken_file(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
        $this->mock(AnalyticsReportExportService::class)->shouldReceive('download')->once()->andThrow(new RuntimeException('disk unavailable'));
        $this->get('/analytics/export?format=xlsx')->assertRedirect(route('analytics.index'))
            ->assertSessionHas('error', 'No se pudo generar la exportación. Inténtalo de nuevo.');
    }
}
