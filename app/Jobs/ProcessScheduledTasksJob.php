<?php

namespace App\Jobs;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $tasks = Schedule::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($tasks as $task) {
            try {
                $this->executeTask($task);
                $task->update(['status' => 'executed', 'executed_at' => now()]);
            } catch (\Throwable $e) {
                $task->update(['status' => 'failed']);
                Log::error("Error ejecutando tarea programada {$task->id}: " . $e->getMessage());
            }
        }

        Log::info("Procesadas {$tasks->count()} tareas programadas.");
    }

    protected function executeTask(Schedule $task): void
    {
        // TODO: Implementar lógica según tipo (power_on, power_off, content_change)
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessScheduledTasksJob falló: ' . $exception->getMessage());
    }
}
