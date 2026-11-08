<?php

namespace Database\Factories;

use App\Enums\EventGender;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        return ['sport_id' => Sport::factory(), 'name' => fake()->unique()->numberBetween(60, 10000).'m', 'gender' => EventGender::Men, 'is_active' => true];
    }
}
