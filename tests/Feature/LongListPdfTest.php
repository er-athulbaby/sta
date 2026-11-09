<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase3Entry;
use App\Jobs\GenerateLongListPdf;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Phases\SubmissionWorkflow;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->shirt = ClothingItem::factory()->create(['name' => 'T-Shirt']);
    $this->game->clothingItems()->attach($this->shirt);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan', 'passport_scan_path' => 'scans/p.pdf']);
    $this->ali->syncSizes([$this->shirt->id => 'L']);
    $this->coach = Person::factory()->official()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim', 'email' => 'coach@example.com']);
    $this->sub = Submission::for($this->game, $this->assoc, 3);
    $this->sub->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'BJF-G001-P3-R1', 'revision' => 1]);
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->ali->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    Entry::create(['submission_id' => $this->sub->id, 'person_id' => $this->coach->id, 'game_event_id' => null, 'game_sport_id' => $this->judo->id]);
});

it('stores the Long List PDF under the reference number', function () {
    (new GenerateLongListPdf($this->sub))->handle();

    $path = $this->sub->fresh()->pdf_paths['long_list'];
    expect($path)->toBe('submissions/BJF-G001-P3-R1/long-list.pdf')
        ->and(substr(Storage::disk(config('filesystems.private'))->get($path), 0, 4))->toBe('%PDF');
});

it('passes athlete and official rows with the spec columns to the view', function () {
    $captured = null;
    view()->composer('pdf.long-list', function ($v) use (&$captured) {
        $captured = $v->getData();
    });

    (new GenerateLongListPdf($this->sub))->handle();

    expect($captured['athleteItems'])->toBe(['T-Shirt'])
        ->and(Arr::except($captured['athletes'][0], 'photo'))->toBe([
            'name' => 'Ali Hasan', 'sport' => 'Judo', 'events' => 'Men -60kg', 'dob' => '10 Mar 2005', 'sizes' => ['L'], 'passport' => true,
        ])
        ->and(Arr::except($captured['officials'][0], 'photo'))->toBe([
            'name' => 'Coach Karim', 'function' => 'Head Coach', 'dob' => '10 Mar 2005', 'phone' => '+973 33000000',
            'email' => 'coach@example.com', 'sizes' => '', 'passport' => false,
        ]);
});

it('renders the header, columns, declaration, boxes and reference', function () {
    $html = view('pdf.long-list', [
        'game' => $this->game, 'association' => $this->assoc, 'athleteItems' => ['T-Shirt'],
        'athletes' => [['photo' => null, 'name' => 'Ali Hasan', 'sport' => 'Judo', 'events' => 'Men -60kg', 'dob' => '10 Mar 2005', 'sizes' => ['L'], 'passport' => true]],
        'officials' => [['photo' => null, 'name' => 'Coach Karim', 'function' => 'Head Coach', 'dob' => '10 Mar 2005', 'phone' => '+973 33000000',
            'email' => 'coach@example.com', 'sizes' => 'T-Shirt: M', 'passport' => false]],
        'referenceNo' => 'BJF-G001-P3-R1', 'declaration' => 'I confirm.', 'nocLogo' => 'data:image/png;base64,AA==', 'gameLogo' => null,
    ])->render();

    expect($html)->toContain('Long List')->toContain('Bahrain Judo Federation')->toContain('Asian Indoor Games')
        ->toContain('Photo')->toContain('Date of birth')->toContain('Passport scan')->toContain('T-Shirt')->toContain('Function')
        ->toContain('Phone')->toContain('Email')->toContain('Sizes')->toContain('Ali Hasan')->toContain('Men -60kg')->toContain('T-Shirt: M')
        ->toContain('I confirm.')->toContain('Signature')->toContain('Stamp')->toContain('BJF-G001-P3-R1');
});

it('is dispatched when a long list is finalised', function () {
    Bus::fake([GenerateLongListPdf::class]);
    Notification::fake();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    Entry::query()->delete();
    $this->sub->update(['status' => SubmissionStatus::Draft]);

    app(SubmissionWorkflow::class)->submit($this->sub->fresh(), User::factory()->assocAdmin($this->assoc)->create());

    Bus::assertDispatched(GenerateLongListPdf::class);
});

it('offers the download on the phase 3 page once submitted', function () {
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->sub->update(['pdf_paths' => ['long_list' => 'submissions/BJF-G001-P3-R1/long-list.pdf']]);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());

    livewire(Phase3Entry::class, ['game' => $this->game->id])->assertSee('Download Long List PDF');
});
