<?php

namespace App\Services;

use Illuminate\Http\Response;

class AnalyticsReportExportService
{
    public function __construct(private TabularExportService $exporter) {}

    public function download(array $report, string $format): Response
    {
        $kpis = $report['kpis'];
        $summary = [
            ['Dispositivos', $kpis['device_count']],
            ['En línea registrados', $kpis['online_count']],
            ['Fuera de línea registrados', $kpis['offline_count']],
            ['Sin estado confirmado', $kpis['unknown_count']],
            ['Horas acumuladas registradas', $kpis['working_hours']],
            ['Dispositivos con horas registradas', $kpis['hours_device_count']],
            ['Campañas', $kpis['total_campaigns']],
            ['Impresiones registradas', $kpis['total_impressions']],
            ['Reproducciones registradas', $kpis['total_plays']],
        ];
        $inventoryHeaders = ['Dispositivos', 'En línea registrados', 'Fuera de línea registrados', 'Sin estado confirmado', 'Horas acumuladas registradas', 'Dispositivos con horas registradas'];
        $inventory = fn (array $row): array => [
            $row['device_count'], $row['online_count'], $row['offline_count'], $row['unknown_count'],
            $row['hours_device_count'] ? $row['working_hours'] : 'Sin datos', $row['hours_device_count'],
        ];
        $sections = [
            ['name' => 'Resumen', 'headers' => ['Métrica', 'Valor'], 'rows' => $summary],
            ['name' => 'Dispositivos', 'headers' => ['Identificador', 'Nombre', 'Modelo', 'Grupo', 'Ciudad', 'Establecimiento', 'Estado registrado', 'Horas acumuladas registradas', 'RPM registrados', 'Reproducciones registradas'],
                'rows' => array_map(fn (array $row): array => [
                    $row['key'], $row['name'], $row['type'], $row['group'], $row['city'], $row['establishment'],
                    ['online' => 'En línea', 'offline' => 'Fuera de línea', 'unknown' => 'Sin estado confirmado'][$row['status']] ?? $row['status'],
                    $row['working_hours'] ?? 'Sin datos', $row['rpm'] ?? 'Sin datos', $row['total_plays'] ?? 'Sin datos',
                ], $report['devices'])],
            ['name' => 'Grupos', 'headers' => ['ID', 'Grupo', ...$inventoryHeaders],
                'rows' => array_map(fn (array $row): array => [$row['id'] ?? 'Sin grupo', $row['group'], ...$inventory($row)], $report['groups'])],
            ['name' => 'Ciudades', 'headers' => ['Ciudad', ...$inventoryHeaders],
                'rows' => array_map(fn (array $row): array => [$row['city'], ...$inventory($row)], $report['cities'])],
            ['name' => 'Campañas', 'headers' => ['ID', 'Campaña', 'Estado guardado', 'Reproducciones registradas', 'Impresiones registradas', 'Registros estadísticos'],
                'rows' => array_map(fn (array $row): array => [
                    $row['id'], $row['campaign'], ['draft' => 'Borrador', 'scheduled' => 'Programada', 'active' => 'Activa', 'paused' => 'Pausada', 'finished' => 'Finalizada'][$row['status']] ?? $row['status'], $row['statistics_count'] ? $row['total_plays'] : 'Sin estadísticas registradas',
                    $row['statistics_count'] ? $row['total_impressions'] : 'Sin estadísticas registradas', $row['statistics_count'],
                ], $report['campaigns'])],
        ];

        return $this->exporter->download($format, 'analiticas-'.now()->format('Y-m-d-His'), 'Analíticas del sistema', $sections, [
            'Inventario actual y estadísticas acumuladas registradas en el sistema.',
            'Los estados corresponden al último registro local, no a una consulta en vivo.',
            'Las horas son acumuladas y sólo incluyen equipos con ese dato. Sin datos indica que no existe telemetría registrada.',
            'Generado: '.now()->format('d/m/Y H:i:s').' ('.config('app.timezone').').',
        ]);
    }
}
