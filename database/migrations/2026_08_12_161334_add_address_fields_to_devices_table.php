<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('establishment')->nullable()->after('name');
            $table->string('contact_name')->nullable()->after('establishment');
            $table->string('contact_phone')->nullable()->after('contact_name');
            $table->string('address')->nullable()->after('contact_phone');
            $table->string('city')->nullable()->after('address');
            $table->string('country')->nullable()->after('city');
            $table->decimal('latitude', 10, 8)->nullable()->after('country');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'establishment',
                'contact_name',
                'contact_phone',
                'address',
                'city',
                'country',
                'latitude',
                'longitude',
            ]);
        });
    }
};
