<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AssociationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Bahrain '.fake()->unique()->word().' Association',
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
