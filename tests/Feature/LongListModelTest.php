<?php

use App\Models\Association;
use App\Models\Comment;
use App\Models\Entry;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\LongListDetail;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->gs = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id])->id]);
    $this->ge = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->gs->id]);
    $this->sub = Submission::for($this->game, $this->assoc, 3);
    $this->athlete = Person::factory()->create(['association_id' => $this->assoc->id]);
});

it('links entries, details and comments to the submission', function () {
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'game_event_id' => $this->ge->id, 'game_sport_id' => $this->gs->id]);
    LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'insurance_company' => 'AXA']);
    Comment::create(['submission_id' => $this->sub->id, 'user_id' => User::factory()->sta()->create()->id, 'body' => 'Check passports']);

    $s = $this->sub->fresh();
    expect($s->entries()->sole()->person->is($this->athlete))->toBeTrue()
        ->and($s->entries()->sole()->gameEvent->is($this->ge))->toBeTrue()
        ->and($s->details()->sole()->insurance_company)->toBe('AXA')
        ->and($s->comments()->sole()->body)->toBe('Check passports');
});

it('encrypts the insurance policy number and keeps it out of the audit log', function () {
    $d = LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'insurance_policy_no' => 'POL-12345']);
    $row = Activity::where('subject_type', LongListDetail::class)->sole();

    expect(DB::table('long_list_details')->where('id', $d->id)->value('insurance_policy_no'))->not->toContain('POL-12345')
        ->and($d->fresh()->insurance_policy_no)->toBe('POL-12345')
        ->and($row->event)->toBe('created')->and(json_encode($row->properties))->not->toContain('POL-12345');
});

it('writes the audit trail of one submission under one log name', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    InterestAnswer::create(['submission_id' => $this->sub->id, 'game_sport_id' => $this->gs->id, 'answer' => 'yes']);
    NumberEntry::create(['submission_id' => $p2->id, 'game_event_id' => $this->ge->id, 'count' => 1]);
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'game_event_id' => $this->ge->id, 'game_sport_id' => $this->gs->id]);
    LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'insurance_company' => 'AXA']);
    $this->sub->update(['justification' => 'x']);

    expect(Activity::where('log_name', 'submission-'.$this->sub->id)->pluck('subject_type')->unique()->sort()->values()->all())
        ->toBe([Entry::class, InterestAnswer::class, LongListDetail::class, Submission::class])
        ->and(Activity::where('log_name', 'submission-'.$p2->id)->pluck('subject_type')->unique()->sort()->values()->all())
        ->toBe([NumberEntry::class, Submission::class]);
});

it('renames the certificate to the person code and deletes it with the row', function () {
    Storage::fake(config('filesystems.private'));
    $disk = Storage::disk(config('filesystems.private'));
    $disk->put('submissions/'.$this->sub->id.'/upload.pdf', '%PDF');

    $d = LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id,
        'insurance_certificate_path' => 'submissions/'.$this->sub->id.'/upload.pdf']);
    $target = 'submissions/'.$this->sub->id.'/'.$this->athlete->code.'_insurance.pdf';

    expect($d->fresh()->insurance_certificate_path)->toBe($target);
    $disk->assertExists($target);
    $d->delete();
    $disk->assertMissing($target);
});

it('logs medical changes of a detail without their values', function () {
    $d = LongListDetail::create(['submission_id' => $this->sub->id, 'person_id' => $this->athlete->id, 'insurance_company' => 'AXA']);
    $d->update(['insurance_policy_no' => 'POL-12345', 'vaccinations' => 'Yellow fever']);

    $a = Activity::where('log_name', 'submission-'.$this->sub->id)->where('subject_type', LongListDetail::class)->where('event', 'updated')->sole();
    expect($a->properties['medical_changed'])->toBeTrue()
        ->and(json_encode($a->properties))->not->toContain('insurance_policy_no')->not->toContain('POL-12345')
        ->not->toContain('vaccinations')->not->toContain('Yellow fever');

    $d->update(['insurance_company' => 'Allianz']);
    $b = Activity::where('subject_type', LongListDetail::class)->where('event', 'updated')->latest('id')->first();
    expect($b->properties->has('medical_changed'))->toBeFalse()->and(json_encode($b->properties))->toContain('Allianz');
});
