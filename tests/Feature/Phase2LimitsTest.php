<?php

use App\Enums\EventGender;
use App\Models\Event;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Sport;
use App\Services\Phases\Phase2Limits;

beforeEach(function () {
    $this->gs = GameSport::factory()->create(['sport_id' => Sport::factory()->create(['name' => 'Judo'])->id,
        'p2_max_men' => 3, 'p2_max_women' => 2, 'p2_max_total' => 4]);
    $mk = fn (string $name, EventGender $g, ?int $max) => GameEvent::factory()->create([
        'game_id' => $this->gs->game_id, 'game_sport_id' => $this->gs->id, 'p2_max_athletes' => $max,
        'event_id' => Event::factory()->create(['sport_id' => $this->gs->sport_id, 'name' => $name, 'gender' => $g])->id]);
    $this->m60 = $mk('Men -60kg', EventGender::Men, 2);
    $this->m73 = $mk('Men -73kg', EventGender::Men, 2);
    $this->w52 = $mk('Women -52kg', EventGender::Women, 1);
    $this->mix = $mk('Mixed team', EventGender::Mixed, 1);
});

it('accepts counts within every limit', function () {
    expect(app(Phase2Limits::class)->errors($this->gs, [$this->m60->id => 2, $this->m73->id => 1, $this->w52->id => 1]))->toBe([]);
});

it('flags an event over its maximum', function () {
    expect(app(Phase2Limits::class)->errors($this->gs, [$this->m60->id => 3]))
        ->toBe(["event.{$this->m60->id}" => 'Men -60kg: at most 2 athletes.']);
});

it('flags sport totals by gender and overall, with mixed only in the total', function () {
    $errors = app(Phase2Limits::class)->errors($this->gs, [$this->m60->id => 2, $this->m73->id => 2, $this->w52->id => 1, $this->mix->id => 1]);

    expect($errors)->toBe([
        'sport.men' => "Judo: men's events total 4, the limit is 3.",
        'sport.total' => 'Judo: total 6, the limit is 4.',
    ]);
});

it('treats a null maximum as no limit and rejects negatives', function () {
    $this->gs->update(['p2_max_men' => null, 'p2_max_total' => null]);
    $this->m60->update(['p2_max_athletes' => null]);

    expect(app(Phase2Limits::class)->errors($this->gs->fresh(), [$this->m60->id => 40, $this->m73->id => -1]))
        ->toBe(["event.{$this->m73->id}" => 'Men -73kg: enter 0 or more.']);
});
