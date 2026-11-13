<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 32px 36px 40px; }
        body { font-family: "DejaVu Sans", sans-serif; color: #0f172a; font-size: 9px; line-height: 1.45; }
        h1 { color: #2d5795; font-size: 21px; margin: 0 0 10px; }
        h2 { color: #2d5795; font-size: 14px; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px; margin: 20px 0 10px; page-break-after: avoid; }
        .note { color: #475569; margin: 3px 0; }
        .record { border-bottom: 1px solid #e2e8f0; padding: 7px 0; }
        .record-compact { page-break-inside: avoid; }
        .record-title { font-weight: bold; color: #475569; margin-bottom: 3px; page-break-after: avoid; }
        .field { margin: 2px 0; white-space: pre-wrap; word-wrap: break-word; }
        .label { font-weight: bold; }
        .empty { color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    @foreach($notes as $note)
        <p class="note">{{ $note }}</p>
    @endforeach
    @foreach($sections as $section)
        <h2>{{ $section['name'] }}</h2>
        @forelse($section['rows'] as $row)
            @php
                // Keep short records together; large details must remain free to span pages.
                $estimatedLines = 2;
                foreach ($section['headers'] as $column => $header) {
                    foreach (preg_split('/\R/u', $header.': '.($row[$column] ?? '')) as $line) {
                        $estimatedLines += max(1, (int) ceil(mb_strlen($line) / 115));
                    }
                }
                $compact = count($section['headers']) <= 12 && $estimatedLines <= 22;
            @endphp
            <div class="record{{ $compact ? ' record-compact' : '' }}">
                <div class="record-title">Registro {{ $loop->iteration }}</div>
                @foreach($section['headers'] as $column => $header)
                    <div class="field"><span class="label">{{ $header }}:</span> {{ $row[$column] ?? '' }}</div>
                @endforeach
            </div>
        @empty
            <p class="empty">Sin registros</p>
        @endforelse
    @endforeach
</body>
</html>
