<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAnalyticsReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 300;

    public function __construct(
        public ?string $startDate = null,
        public ?string $endDate = null
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        Log::info('Generando reporte de analíticas...');

        // TODO: Generar reporte de analíticas
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateAnalyticsReportJob falló: ' . $exception->getMessage());
    }
}
