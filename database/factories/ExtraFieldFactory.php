<?php

namespace Database\Factories;

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExtraFieldFactory extends Factory
{
    public function definition(): array
    {
        return ['label' => fake()->unique()->words(2, true), 'applies_to' => AppliesTo::Both, 'type' => ExtraFieldType::Text,
            'required' => false, 'sensitive' => false, 'is_active' => true];
    }
}
