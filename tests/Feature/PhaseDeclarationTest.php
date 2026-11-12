<?php

use App\Enums\EventGender;
use App\Enums\PersonGender;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Pages\Settings;
use App\Jobs\GenerateEntryByEventPdfs;
use App\Jobs\GenerateEntryBySportPdf;
use App\Jobs\GenerateLongListPdf;
use App\Jobs\GenerateShortListPdf;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

/** Final wave item 13: a declaration per phase on the PDFs, falling back to the single declaration. */
function declarationOnPdf(Submission $s, string $view, string $job): ?string
{
    $captured = null;
    view()->composer($view, function ($v) use (&$captured) {
        $captured = $v->getData()['declaration'];
    });
    (new $job($s))->handle();

    return $captured;
}

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $gs = GameSport::factory()->create(['game_id' => $this->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $ge = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'gender' => EventGender::Men])->id]);
    $ali = Person::factory()->create(['association_id' => $this->assoc->id, 'gender' => PersonGender::Male]);
    foreach ([1, 2, 3, 4] as $n) {
        $s = Submission::for($this->game, $this->assoc, $n);
        if ($n >= 3) {
            $s->entries()->create(['person_id' => $ali->id, 'game_event_id' => $ge->id, 'game_sport_id' => $gs->id]);
        }
        $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => "BJF-G001-P{$n}-R1", 'revision' => 1]);
        $this->{"p$n"} = $s;
    }
    $this->pdfs = [1 => ['pdf.entry-by-sport', GenerateEntryBySportPdf::class], 2 => ['pdf.entry-by-event', GenerateEntryByEventPdfs::class],
        3 => ['pdf.long-list', GenerateLongListPdf::class], 4 => ['pdf.long-list', GenerateShortListPdf::class]];
});

it('prints each phase\'s own declaration on its PDF', function () {
    foreach ([1, 2, 3, 4] as $n) {
        Setting::put(Setting::phaseDeclarationKey($n), "Phase {$n} wording.");
    }

    foreach ($this->pdfs as $n => [$view, $job]) {
        expect(declarationOnPdf($this->{"p$n"}, $view, $job))->toBe("Phase {$n} wording.");
    }
});

it('falls back to the single declaration, then the built-in text, when a phase text is empty', function () {
    expect(Setting::declaration(2))->toBe(Setting::DEFAULT_DECLARATION);
    Setting::put(Setting::DECLARATION, 'Shared wording.');
    Setting::put(Setting::phaseDeclarationKey(3), 'Long List wording.');

    expect(declarationOnPdf($this->p2, ...$this->pdfs[2]))->toBe('Shared wording.')
        ->and(declarationOnPdf($this->p3, ...$this->pdfs[3]))->toBe('Long List wording.');
});

it('edits the four phase declarations on Settings, empty ones showing the single text, and only with manage_master_data', function () {
    Setting::put(Setting::DECLARATION, 'Current wording.');
    Setting::put(Setting::phaseDeclarationKey(3), 'Stored three.');
    $sta = User::factory()->sta()->create();
    actingInAdmin($sta);
    $this->get('/admin/settings')->assertForbidden();

    $sta->givePermissionTo(StaPermission::ManageMasterData->value);
    livewire(Settings::class)
        ->assertSchemaStateSet(['phase_declarations' => [1 => null, 2 => null, 3 => 'Stored three.', 4 => null]])
        ->assertSee('Phase 4 declaration (Short List PDF)')->assertSeeHtml('placeholder="Current wording."')
        ->fillForm(['phase_declarations' => [1 => 'One.', 2 => '', 3 => 'Three.', 4 => 'Four.']])->call('save')->assertHasNoFormErrors();

    expect(Setting::declaration(1))->toBe('One.')->and(Setting::declaration(2))->toBe('Current wording.')
        ->and(Setting::declaration(4))->toBe('Four.');
});

it('keeps empty phases following the single text after Settings is saved', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(Settings::class)->call('save')->assertHasNoFormErrors(); // every phase field left empty
    livewire(Settings::class)->fillForm(['pdf_declaration' => 'New single wording.'])->call('save')->assertHasNoFormErrors();

    expect(Setting::get(Setting::phaseDeclarationKey(2)))->toBeNull()
        ->and(declarationOnPdf($this->p2, ...$this->pdfs[2]))->toBe('New single wording.');
});
