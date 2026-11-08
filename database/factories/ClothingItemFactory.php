<?php

namespace Database\Factories;

use App\Enums\AppliesTo;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClothingItemFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'sizes' => ['S', 'M', 'L', 'XL'], 'applies_to' => AppliesTo::Both, 'is_active' => true];
    }
}
