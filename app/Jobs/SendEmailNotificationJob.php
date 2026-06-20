<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 60;

    public function __construct(
        public string $to,
        public string $subject,
        public string $view,
        public array $data = []
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        Mail::send($this->view, $this->data, function ($message) {
            $message->to($this->to)->subject($this->subject);
        });

        Log::info("Correo enviado a {$this->to}: {$this->subject}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendEmailNotificationJob falló: ' . $exception->getMessage());
    }
}
