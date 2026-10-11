<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', fn (Blueprint $table) => $table->json('target_devices')->nullable());
    }

    public function down(): void
    {
        if (DB::table('campaigns')->whereNotNull('target_devices')->exists()) {
            throw new RuntimeException('Exporta y resuelve las selecciones explícitas antes de retirar target_devices; el pivote anterior no representa WL35 ni una selección vacía.');
        }
        Schema::table('campaigns', fn (Blueprint $table) => $table->dropColumn('target_devices'));
    }
};
