<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\GameEvent;
use App\Models\GameSport;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameEventFactory extends Factory
{
    // game_id / event_id are derived from the game sport in afterMaking when not given explicitly.
    public function definition(): array
    {
        return ['game_sport_id' => GameSport::factory()];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (GameEvent $ge) {
            $gs = GameSport::find($ge->game_sport_id);
            $ge->game_id ??= $gs->game_id;
            $ge->event_id ??= Event::factory()->create(['sport_id' => $gs->sport_id])->id;
        });
    }
}
