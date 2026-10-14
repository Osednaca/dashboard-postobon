<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'tecnico',
        ];
    }

    public function superadmin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'superadmin']);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function tecnico(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'tecnico',
        ]);
    }

    public function campanas(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'campanas']);
    }

    public function operator(): static
    {
        return $this->tecnico();
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
