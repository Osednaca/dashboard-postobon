<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class TabularExportService
{
    /**
     * @param  array<int, array{name: string, headers: array, rows: iterable}>  $sections
     * @param  array<int, string>  $notes
     */
    public function download(string $format, string $basename, string $title, array $sections, array $notes = []): Response
    {
        if (! in_array($format, ['pdf', 'xlsx'], true)) {
            throw new InvalidArgumentException('Formato de exportación no válido.');
        }

        $basename = trim(preg_replace('/[^A-Za-z0-9_-]/', '-', $basename), '-') ?: 'reporte';
        $sections = array_map(function (array $section): array {
            $section['name'] = $this->text($section['name']);
            $section['headers'] = array_map($this->text(...), array_values($section['headers']));
            $section['rows'] = collect($section['rows'])->map(fn ($row): array => array_map(fn ($value) => is_int($value) || is_float($value) ? $value : $this->text($value), array_values((array) $row)))->all();

            return $section;
        }, $sections ?: [['name' => 'Datos', 'headers' => ['Información'], 'rows' => []]]);
        $title = $this->text($title);
        $notes = array_map($this->text(...), $notes);
        $content = $format === 'pdf'
            ? $this->pdf($title, $sections, $notes)
            : $this->xlsx($title, $sections, $notes);

        return response($content, 200, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$basename.'.'.$format.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function pdf(string $title, array $sections, array $notes): string
    {
        $directory = storage_path('framework/cache/pdf');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar el reporte PDF.');
        }
        $options = new Options([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'tempDir' => $directory,
            'fontCache' => $directory,
            'chroot' => base_path('vendor/dompdf/dompdf/lib/fonts'),
        ]);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('exports.tabular-pdf', compact('title', 'sections', 'notes'))->render(), 'UTF-8');
        $pdf->setPaper('a4', 'landscape');
        $pdf->render();
        $pdf->getCanvas()->page_text(36, 570, 'Página {PAGE_NUM} de {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.3, 0.3, 0.3]);

        return $pdf->output();
    }

    private function xlsx(string $title, array $sections, array $notes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dashboard-export-');
        if ($path === false) {
            throw new RuntimeException('No se pudo preparar el reporte Excel.');
        }
        $zip = new ZipArchive;
        $opened = false;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el reporte Excel.');
            }
            $opened = true;
            $sheets = '';
            $relationships = '';
            $overrides = '';
            foreach (array_values($sections) as $index => $section) {
                $id = $index + 1;
                $name = $id.' '.mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', '-', $section['name']), 0, 27);
                $sheets .= '<sheet name="'.$this->xml($name).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
                $relationships .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
                $overrides .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', $this->worksheet($title, $section, $notes));
            }
            $zip->addFromString('[Content_Types].xml', $this->document('<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>'));
            $zip->addFromString('_rels/.rels', $this->document('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'));
            $zip->addFromString('xl/workbook.xml', $this->document('<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>'));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->document('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'<Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'));
            $zip->addFromString('xl/styles.xml', $this->document('<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>'));
            $zip->close();
            $opened = false;
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('No se pudo leer el reporte Excel.');
            }

            return $content;
        } finally {
            if ($opened) {
                $zip->close();
            }
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function worksheet(string $title, array $section, array $notes): string
    {
        $rows = array_merge([[$title]], array_map(fn ($note): array => [$note], $notes), [[$section['name']], $section['headers']]);
        $headerRow = count($rows);
        foreach ($section['rows'] ?: [['Sin registros']] as $row) {
            // Excel limits individual cells to 32767 characters; preserve overflow in continuation rows.
            $parts = array_map(fn ($value): array => is_string($value) ? mb_str_split($value, 32767) : [$value], $row);
            $count = max([1, ...array_map('count', $parts)]);
            for ($part = 0; $part < $count; $part++) {
                $rows[] = array_map(fn ($values) => $values[$part] ?? '', $parts);
            }
        }
        $data = '';
        foreach ($rows as $index => $row) {
            $number = $index + 1;
            $data .= '<row r="'.$number.'">';
            foreach ($row as $column => $value) {
                $reference = $this->column($column + 1).$number;
                $style = $number <= $headerRow ? 1 : 0;
                $data .= is_int($value) || is_float($value)
                    ? '<c r="'.$reference.'" t="n" s="'.$style.'"><v>'.$value.'</v></c>'
                    : '<c r="'.$reference.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.$this->xml($value).'</t></is></c>';
            }
            $data .= '</row>';
        }
        $lastColumn = $this->column(max(1, count($section['headers'])));

        return $this->document('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="'.max(1, count($section['headers'])).'" width="28" customWidth="1"/></cols><sheetData>'.$data.'</sheetData><autoFilter ref="A'.$headerRow.':'.$lastColumn.count($rows).'"/></worksheet>');
    }

    private function column(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + $number % 26).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function document(string $xml): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$xml;
    }

    private function text(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', mb_convert_encoding((string) ($value ?? ''), 'UTF-8', 'UTF-8'));
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($this->text($value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
