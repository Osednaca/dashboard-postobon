<?php

namespace Tests\Unit;

use App\Services\TabularExportService;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class TabularExportServiceTest extends TestCase
{
    private function files(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'export-test-');
        file_put_contents($path, $content);
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path) === true);
            $files = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                $files[$name] = $zip->getFromIndex($index);
                $this->assertNotFalse(simplexml_load_string($files[$name]), $name);
            }
            $zip->close();

            return $files;
        } finally {
            unlink($path);
        }
    }

    public function test_excel_has_valid_relationships_numeric_values_and_literal_untrusted_text(): void
    {
        $response = app(TabularExportService::class)->download('xlsx', 'reporte-real', 'Reporte Bogotá', [[
            'name' => 'Datos / Bogotá', 'headers' => ['Nombre', 'Horas', 'Código'],
            'rows' => [['=HYPERLINK("https://example.invalid")', 12.75, '00042'], ['<script>niño & acción</script>', 3, ' +SUM(A1)']],
        ]], ['Inventario registrado']);
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="reporte-real.xlsx"', $response->headers->get('Content-Disposition'));
        $files = $this->files($response->getContent());
        $this->assertArrayHasKey('[Content_Types].xml', $files);
        $this->assertStringContainsString('worksheets/sheet1.xml', $files['xl/_rels/workbook.xml.rels']);
        $sheet = simplexml_load_string($files['xl/worksheets/sheet1.xml']);
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $this->assertCount(0, $sheet->xpath('//s:f'));
        $this->assertSame('=HYPERLINK("https://example.invalid")', (string) $sheet->xpath('//s:c[@r="A5"]/s:is/s:t')[0]);
        $this->assertSame('12.75', (string) $sheet->xpath('//s:c[@r="B5"]/s:v')[0]);
        $this->assertSame('n', (string) $sheet->xpath('//s:c[@r="B5"]')[0]['t']);
        $this->assertSame('00042', (string) $sheet->xpath('//s:c[@r="C5"]/s:is/s:t')[0]);
        $this->assertSame('<script>niño & acción</script>', (string) $sheet->xpath('//s:c[@r="A6"]/s:is/s:t')[0]);
        $this->assertSame('A4:C6', (string) $sheet->autoFilter['ref']);
        $this->assertSame('4', (string) $sheet->sheetViews->sheetView->pane['ySplit']);
        $this->assertStringContainsString('1 Datos - Bogotá', $files['xl/workbook.xml']);
    }

    public function test_excel_preserves_long_cells_in_continuations_and_empty_sections(): void
    {
        $long = str_repeat('á', 33000);
        $response = app(TabularExportService::class)->download('xlsx', 'auditoria', 'Auditoría', [
            ['name' => 'Detalles', 'headers' => ['Dato'], 'rows' => [[$long]]],
            ['name' => 'Sin resultados', 'headers' => ['Dato'], 'rows' => []],
        ]);
        $files = $this->files($response->getContent());
        $sheet = simplexml_load_string($files['xl/worksheets/sheet1.xml']);
        $this->assertSame($long, (string) $sheet->sheetData->row[3]->c->is->t.(string) $sheet->sheetData->row[4]->c->is->t);
        $this->assertStringContainsString('Sin registros', $files['xl/worksheets/sheet2.xml']);
        $this->assertStringNotContainsString('<f>', implode('', $files));
    }

    public function test_pdf_is_a_real_multipage_document_with_embedded_unicode_font(): void
    {
        $rows = array_map(fn ($number): array => ['Establecimiento Bogotá '.$number, str_repeat('Acción registrada; detalle amplio. ', 20)], range(1, 45));
        $response = app(TabularExportService::class)->download('pdf', 'analiticas', 'Analíticas del sistema', [[
            'name' => 'Auditoría', 'headers' => ['Nombre', 'Detalles'], 'rows' => $rows,
        ]], ['Información acumulada']);
        $pdf = $response->getContent();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="analiticas.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
        $this->assertStringContainsString('DejaVuSans', $pdf);
    }

    public function test_pdf_handles_empty_data_and_html_is_escaped_in_the_template(): void
    {
        $section = ['name' => 'Inventario', 'headers' => ['Nombre'], 'rows' => [['<img src="https://example.invalid/image" onerror="alert(1)">']]];
        $html = view('exports.tabular-pdf', ['title' => 'Reporte', 'notes' => [], 'sections' => [$section]])->render();
        $this->assertStringNotContainsString('<img src=', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $response = app(TabularExportService::class)->download('pdf', 'vacio', 'Sin datos', []);
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_keeps_a_short_record_together_when_it_reaches_a_page_boundary(): void
    {
        $rows = [
            ['13/11/2026 10:00:00', '=SUM(A1:A2)', '<b>acción histórica</b>', 'Wl35DeviceProfile', 13,
                json_encode(['ciudad' => 'Bogotá', 'nombre' => 'María Ñandú', 'entrada' => '<script>alert("audit")</script>'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'IP-FINAL-1'],
            ['13/11/2026 10:00:00', 'Sin usuario disponible', 'publish', 'Campaign', 12, 'Sin detalles registrados', 'Sin IP registrada'],
            ['13/11/2026 10:00:00', 'Sin usuario disponible', 'publish', 'Campaign', 11,
                json_encode(['etiqueta' => 'Bogotá'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'IP-FINAL-3'],
        ];
        $pdf = app(TabularExportService::class)->download('pdf', 'auditoria', 'Auditoría', [[
            'name' => 'Historial de auditoría', 'headers' => ['Fecha', 'Usuario', 'Acción registrada', 'Entidad registrada', 'ID', 'Detalles', 'IP'], 'rows' => $rows,
        ]], ['Todos los resultados de los filtros aplicados. Zona horaria: America/Bogota. Total: 3.'])->getContent();
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
        $recordPage = null;
        foreach ($streams[1] as $stream) {
            $content = @gzuncompress($stream);
            if ($content !== false && str_contains($content, mb_convert_encoding('Registro 3', 'UTF-16BE', 'UTF-8'))) {
                $recordPage = $content;
                break;
            }
        }
        $this->assertNotNull($recordPage, 'Expected the actual PDF page stream containing record 3.');
        $this->assertTrue(str_contains($recordPage, mb_convert_encoding('IP-FINAL-3', 'UTF-16BE', 'UTF-8')), 'The last field must share the PDF page with its short record heading.');
    }

    public function test_unknown_formats_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(TabularExportService::class)->download('csv', 'reporte', 'Reporte', []);
    }
}
