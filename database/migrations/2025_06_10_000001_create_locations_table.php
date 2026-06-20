<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('city');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
