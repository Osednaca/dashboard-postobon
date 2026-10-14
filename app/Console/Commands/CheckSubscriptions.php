<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check';

    protected $description = 'Comando retirado; conserva las suscripciones históricas sin modificarlas';

    public function handle(): int
    {
        $this->info('El módulo de suscripciones está retirado. No se modificaron datos ni se enviaron alertas.');

        return self::SUCCESS;
    }
}
