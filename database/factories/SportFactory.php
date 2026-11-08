<?php

namespace Database\Factories;

use App\Models\Association;
use Illuminate\Database\Eloquent\Factories\Factory;

class SportFactory extends Factory
{
    public function definition(): array
    {
        return ['association_id' => Association::factory(), 'name' => fake()->unique()->word().' Sport', 'is_active' => true];
    }
}
