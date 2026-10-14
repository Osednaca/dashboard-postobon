<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->foreignId('business_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address', 500)->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('google_place_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->dropForeign(['business_type_id']);
            $table->dropColumn(['business_type_id', 'address', 'city', 'latitude', 'longitude', 'google_place_id']);
        });
    }
};
