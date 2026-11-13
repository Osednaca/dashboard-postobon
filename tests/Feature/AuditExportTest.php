<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TabularExportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class AuditExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Bogota']);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->superadmin()->create());
    }

    private function entry(array $attributes = [], string $date = '2026-11-13 10:00:00'): AuditLog
    {
        $log = AuditLog::create(array_replace([
            'user_id' => auth()->id(), 'action' => 'publish', 'entity_type' => 'Campaign',
            'entity_id' => 573, 'details' => ['etiqueta' => 'Bogotá'], 'ip_address' => '203.0.113.27',
        ], $attributes));
        $log->forceFill(['created_at' => $date])->save();

        return $log;
    }

    private function sheetRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'audit-export-test-');
        file_put_contents($path, $content);
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path) === true);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringNotContainsString('<f>', $xml);
            $sheet = simplexml_load_string($xml);
            $this->assertNotFalse($sheet);
            $rows = [];
            foreach ($sheet->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $values[] = (string) $cell['t'] === 'inlineStr' ? (string) $cell->is->t : (string) $cell->v;
                }
                $rows[] = $values;
            }

            return $rows;
        } finally {
            $zip->close();
            unlink($path);
        }
    }

    private function records(array $rows): array
    {
        return array_values(array_filter($rows, fn ($row): bool => count($row) === 7 && ctype_digit((string) $row[4])));
    }

    private function sample(string $filename, string $content): void
    {
        $directory = storage_path('framework/testing/analytics-exports-visual');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($directory.'/'.$filename, $content);
    }

    public function test_selector_preserves_only_applied_valid_filters_and_exports_all_pages_in_stable_order(): void
    {
        $actor = User::factory()->create(['name' => 'María responsable']);
        for ($index = 1; $index <= 18; $index++) {
            $this->entry(['user_id' => $actor->id, 'entity_id' => $index, 'details' => ['fila' => $index]]);
        }
        $this->entry(['action' => 'unmatched', 'entity_id' => 999]);
        $filters = ['user_id' => $actor->id, 'action' => 'publish', 'entity_type' => 'Campaign', 'date' => '2026-11-13'];
        $response = $this->get('/audit?'.http_build_query($filters + ['page' => 2, 'irrelevant' => 'discard']))->assertOk()
            ->assertSee('Formato de exportación')->assertSee('Excel (.xlsx)')->assertSee(route('audit.export'));
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $inputs = $xpath->query('//form[@action="'.route('audit.export').'"]//input[@type="hidden"]');
        $actual = [];
        foreach ($inputs as $input) {
            $actual[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        $this->assertSame(array_map('strval', $filters), $actual);
        $response->assertViewHas('logs', fn ($logs): bool => $logs->count() === 3 && $logs->total() === 18);
        $export = $this->get('/audit/export?'.http_build_query($filters + ['format' => 'xlsx', 'page' => 2]))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="auditoria.xlsx"');
        $records = $this->records($this->sheetRows($export->getContent()));
        $this->assertCount(18, $records);
        $this->assertSame(array_map('strval', range(18, 1)), array_column($records, 4));
        $this->assertSame(array_fill(0, 18, 'María responsable'), array_column($records, 1));
        Http::assertNothingSent();
    }

    public function test_each_filter_and_local_date_boundaries_match_the_screen_query(): void
    {
        $actor = User::factory()->create();
        $this->entry(['user_id' => $actor->id, 'entity_id' => 1], '2026-11-12 23:59:59');
        $this->entry(['user_id' => $actor->id, 'entity_id' => 2], '2026-11-13 00:00:00');
        $this->entry(['user_id' => $actor->id, 'entity_id' => 3], '2026-11-13 23:59:59');
        $this->entry(['user_id' => $actor->id, 'entity_id' => 4], '2026-11-14 00:00:00');
        $this->entry(['action' => 'unknown_historical_action', 'entity_type' => 'HistoricalType', 'entity_id' => 5]);
        foreach ([['user_id' => $actor->id], ['action' => 'unknown_historical_action'], ['entity_type' => 'HistoricalType'],
            ['date' => '2026-11-13'], ['user_id' => $actor->id, 'action' => 'publish', 'entity_type' => 'Campaign', 'date' => '2026-11-13']] as $filters) {
            $page = $this->get('/audit?'.http_build_query($filters))->assertOk();
            $expected = $page->viewData('logs')->pluck('entity_id')->map('strval')->all();
            $file = $this->get('/audit/export?'.http_build_query($filters + ['format' => 'xlsx']))->assertOk();
            $this->assertSame($expected, array_column($this->records($this->sheetRows($file->getContent())), 4));
        }
        $file = $this->get('/audit/export?format=xlsx&date=2026-11-13&user_id='.$actor->id)->assertOk();
        $records = $this->records($this->sheetRows($file->getContent()));
        $this->assertSame(['3', '2'], array_column($records, 4));
        $this->assertSame(['13/11/2026 23:59:59', '13/11/2026 00:00:00'], array_column($records, 0));
        Http::assertNothingSent();
    }

    public function test_exports_keep_unicode_literal_text_json_and_missing_users_without_writing_or_http(): void
    {
        $actor = User::factory()->create(['name' => '=SUM(A1:A2)']);
        $deleted = User::factory()->create();
        $this->entry(['user_id' => $deleted->id, 'entity_id' => 11]);
        $deleted->delete();
        $this->entry(['user_id' => null, 'entity_id' => 12, 'details' => null, 'ip_address' => null]);
        $this->entry(['user_id' => $actor->id, 'entity_id' => 13,
            'action' => '<b>acción histórica</b>', 'entity_type' => 'Wl35DeviceProfile',
            'details' => ['ciudad' => 'Bogotá', 'nombre' => 'María Ñandú', 'entrada' => '<script>alert("audit")</script>']]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $xlsx = $this->get('/audit/export?format=xlsx')->assertOk();
        $records = $this->records($this->sheetRows($xlsx->getContent()));
        $this->assertSame('=SUM(A1:A2)', $records[0][1]);
        $this->assertSame('<b>acción histórica</b>', $records[0][2]);
        $this->assertStringContainsString('Bogotá', $records[0][5]);
        $this->assertStringContainsString('María Ñandú', $records[0][5]);
        $this->assertStringContainsString('<script>alert(', $records[0][5]);
        $this->assertSame('Sin usuario disponible', $records[1][1]);
        $this->assertSame('Sin detalles registrados', $records[1][5]);
        $this->assertSame('Sin IP registrada', $records[1][6]);
        $this->assertSame('Sin usuario disponible', $records[2][1]);
        $pdf = $this->get('/audit/export?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="auditoria.pdf"');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringNotContainsString('/JavaScript', $pdf->getContent());
        $this->assertStringNotContainsString('/URI', $pdf->getContent());
        $this->sample('audit-real.xlsx', $xlsx->getContent());
        $this->sample('audit-real.pdf', $pdf->getContent());
        $this->assertEmpty(array_filter($queries, fn ($sql) => preg_match('/^\s*(insert|update|delete)\b/i', $sql)));
        Http::assertNothingSent();
    }

    public function test_empty_filtered_exports_are_valid_without_inventing_events(): void
    {
        $this->entry();
        $xlsx = $this->get('/audit/export?format=xlsx&action=no_match')->assertOk();
        $rows = $this->sheetRows($xlsx->getContent());
        $this->assertSame([], $this->records($rows));
        $this->assertContains(['Sin registros'], $rows);
        $this->get('/audit/export?format=pdf&action=no_match')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, AuditLog::count());
        Http::assertNothingSent();
    }

    public function test_permissions_and_validation_apply_to_both_export_formats(): void
    {
        foreach (['admin', 'campanas', 'tecnico'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['pdf', 'xlsx'] as $format) {
                $this->get('/audit/export?format='.$format)->assertForbidden();
            }
        }
        $this->app['auth']->forgetGuards();
        $this->get('/audit/export?format=pdf')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->superadmin()->create());
        foreach ([[], ['format' => 'csv'], ['format' => ['pdf']], ['format' => 'xlsx', 'user_id' => 999999],
            ['format' => 'xlsx', 'user_id' => [1]], ['format' => 'xlsx', 'action' => ['publish']],
            ['format' => 'xlsx', 'entity_type' => ['Device']], ['format' => 'xlsx', 'date' => '2026-02-30'],
            ['format' => 'xlsx', 'date' => '0000-01-01']] as $query) {
            $this->getJson('/audit/export?'.http_build_query($query))->assertUnprocessable();
        }
        // Pagination and unknown query keys do not limit the exported result set.
        $this->get('/audit/export?format=xlsx&page[]=invalid&unknown=discard')->assertOk();
        Http::assertNothingSent();
    }

    public function test_renderer_failure_returns_to_the_same_filters_with_a_useful_error(): void
    {
        $this->mock(TabularExportService::class)->shouldReceive('download')->once()->andThrow(new RuntimeException('Fixture disk error'));
        $this->get('/audit/export?format=xlsx&action=publish&page=2')->assertRedirect(route('audit.index', ['action' => 'publish']))
            ->assertSessionHas('error', 'No se pudo generar la exportación de auditoría. Inténtalo de nuevo.');
        Http::assertNothingSent();
    }
}
