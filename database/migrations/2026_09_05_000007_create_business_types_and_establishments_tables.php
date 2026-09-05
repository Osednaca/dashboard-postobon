<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('establishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('address', 500);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('wifi_ssid')->nullable();
            $table->text('wifi_password')->nullable();
            $table->timestamps();

            $table->index(['business_type_id', 'name']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('establishment_id')
                ->nullable()
                ->after('name')
                ->constrained()
                ->restrictOnDelete();
        });

        if (Schema::hasTable('wl35_device_profiles')) {
            Schema::table('wl35_device_profiles', function (Blueprint $table) {
                $table->foreignId('establishment_id')
                    ->nullable()
                    ->after('name')
                    ->constrained()
                    ->restrictOnDelete();
            });
        }

        $now = now();
        $otherTypeId = DB::table('business_types')->insertGetId([
            'name' => 'Otro',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('business_types')->insert([
            ['name' => 'Cafetería', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Restaurante', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->backfillLegacyEstablishments('devices', $otherTypeId);

        if (Schema::hasTable('wl35_device_profiles')) {
            $this->backfillLegacyEstablishments('wl35_device_profiles', $otherTypeId);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wl35_device_profiles') && Schema::hasColumn('wl35_device_profiles', 'establishment_id')) {
            Schema::table('wl35_device_profiles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('establishment_id');
            });
        }

        if (Schema::hasColumn('devices', 'establishment_id')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('establishment_id');
            });
        }

        Schema::dropIfExists('establishments');
        Schema::dropIfExists('business_types');
    }

    private function backfillLegacyEstablishments(string $table, int $businessTypeId): void
    {
        DB::table($table)
            ->whereNotNull('establishment')
            ->where('establishment', '!=', '')
            ->orderBy('id')
            ->get()
            ->each(function (object $legacy) use ($table, $businessTypeId): void {
                $name = trim((string) $legacy->establishment);
                $existing = DB::table('establishments')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->first();

                $establishmentId = $existing?->id ?? DB::table('establishments')->insertGetId([
                    'business_type_id' => $businessTypeId,
                    'name' => $name,
                    'contact_name' => $legacy->contact_name ?? null,
                    'contact_phone' => $legacy->contact_phone ?? null,
                    'address' => trim((string) ($legacy->address ?? '')) ?: 'Dirección pendiente',
                    'latitude' => $legacy->latitude ?? null,
                    'longitude' => $legacy->longitude ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table($table)->where('id', $legacy->id)->update([
                    'establishment_id' => $establishmentId,
                ]);
            });
    }
};
