<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => '6th Asian Indoor & Martial Arts Games 2026',
            'start_date' => now()->addMonths(3),
            'end_date' => now()->addMonths(3)->addDays(10),
            'reminder_days' => [7, 3, 0],
        ];
    }
}
