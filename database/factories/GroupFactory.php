<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        $groupTypes = [
            'Retail', 'Shopping Malls', 'Supermarkets', 'Airports', 'Stadiums',
            'Cinema', 'Restaurants', 'Hotels', 'Banks', 'Pharmacies',
            'Gas Stations', 'Universities', 'Hospitals', 'Offices', 'Gyms',
        ];

        $regionSuffixes = [
            'Norte', 'Sur', 'Oriente', 'Occidente', 'Centro',
            'Costa', 'Andina', 'Pacífica', 'Caribe', 'Amazonía',
            'Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Nacional',
        ];

        return [
            'name' => fake()->randomElement($groupTypes) . ' ' . fake()->randomElement($regionSuffixes),
            'description' => fake()->optional(0.7)->paragraph(),
        ];
    }
}
