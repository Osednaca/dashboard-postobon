<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('audience_mode', 20)->nullable();
            $table->json('audience_business_type_ids')->nullable();
            $table->unsignedBigInteger('audience_establishment_id')->nullable();
        });
    }

    public function down(): void
    {
        // Exact recipients remain in target_devices when criteria are removed.
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn(['audience_mode', 'audience_business_type_ids', 'audience_establishment_id']);
        });
    }
};
