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
    $this->limits = app(Phase2Limits::class);
});

it('limits event counts only by each event maximum', function () {
    // 6 event entries > p2_max_total 4, but the sport limits no longer apply to event sums.
    expect($this->limits->errors($this->gs, [$this->m60->id => 2, $this->m73->id => 2, $this->w52->id => 1, $this->mix->id => 1]))->toBe([]);
});

it('flags an event over its maximum', function () {
    expect($this->limits->errors($this->gs, [$this->m60->id => 3]))
        ->toBe(["event.{$this->m60->id}" => 'Men -60kg: at most 2 athletes.']);
});

it('applies the sport limits to the participant totals', function () {
    expect($this->limits->errors($this->gs, [], ['male' => 4, 'female' => 1]))->toBe([
        'sport.male' => 'Judo: 4 male participants, the limit is 3.',
        'sport.total' => 'Judo: 5 participants in total, the limit is 4.',
    ])->and($this->limits->errors($this->gs, [], ['male' => 1, 'female' => 3]))->toBe([
        'sport.female' => 'Judo: 3 female participants, the limit is 2.',
    ])->and($this->limits->errors($this->gs, [], ['male' => 3, 'female' => 1]))->toBe([]);
});

it('skips blank totals and null limits and rejects negative counts', function () {
    $this->gs->update(['p2_max_men' => null, 'p2_max_total' => null]);
    $this->m60->update(['p2_max_athletes' => null]);

    expect($this->limits->errors($this->gs->fresh(), [$this->m60->id => 40, $this->m73->id => -1], ['male' => 500]))
        ->toBe(["event.{$this->m73->id}" => 'Men -73kg: enter 0 or more.'])
        ->and($this->limits->errors($this->gs->fresh(), [], []))->toBe([]);
});
