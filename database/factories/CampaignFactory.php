<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $statuses = ['draft', 'scheduled', 'active', 'paused', 'finished'];
        $status = fake()->randomElement($statuses);
        $startDate = null;
        $endDate = null;

        if (in_array($status, ['scheduled', 'active', 'paused', 'finished'])) {
            $startDate = fake()->dateTimeBetween('-30 days', '+30 days');
            if (in_array($status, ['finished', 'paused'])) {
                $endDate = fake()->dateTimeBetween($startDate, '+60 days');
            } else {
                $endDate = fake()->randomElement([null, fake()->dateTimeBetween($startDate, '+60 days')]);
            }
        }

        $cities = [
            'Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena',
            'Bucaramanga', 'Pereira', 'Manizales', 'Bogotá', 'Medellín',
        ];
        $segmentCities = fake()->randomElements($cities, fake()->numberBetween(1, 4));
        $segmentGroups = fake()->randomElements(
            ['Retail', 'Malls', 'Airports', 'Stadiums', 'Cinema', 'Restaurants'],
            fake()->numberBetween(0, 3)
        );

        return [
            'name' => fake()->catchPhrase() . ' Campaign ' . fake()->year(),
            'description' => fake()->optional(0.8)->paragraph(2),
            'status' => $status,
            'priority' => fake()->numberBetween(0, 10),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'segment_cities' => $segmentCities,
            'segment_groups' => $segmentGroups,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'start_date' => fake()->dateTimeBetween('-30 days', '-1 day'),
            'end_date' => fake()->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finished',
            'start_date' => fake()->dateTimeBetween('-90 days', '-30 days'),
            'end_date' => fake()->dateTimeBetween('-29 days', '-1 day'),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'start_date' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'end_date' => fake()->dateTimeBetween('+31 days', '+90 days'),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
            'start_date' => fake()->dateTimeBetween('-30 days', '-1 day'),
            'end_date' => fake()->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }
}
