<?php

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Filament\Pages\Dashboard;
use App\Filament\Portal\Pages\Home;
use App\Models\Association;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

// The "General dashboard" tab of the admin home and the portal home.
beforeEach(function () {
    $this->a = Association::factory()->create(['name' => 'Alpha Federation', 'code' => 'ALP']);
    $this->b = Association::factory()->create(['name' => 'Beta Federation', 'code' => 'BET']);
    Person::factory()->create(['association_id' => $this->a->id, 'first_name' => 'Amal', 'last_name' => 'Alpha', 'photo_path' => 'photos/amal.jpg',
        'gender' => PersonGender::Female, 'dob' => now()->subYears(16)->toDateString(), 'passport_expiry' => now()->addMonth()->toDateString()]);
    Person::factory()->official()->create(['association_id' => $this->a->id, 'first_name' => 'Omar', 'last_name' => 'Alpha',
        'dob' => now()->subYears(40)->toDateString(), 'nationality' => 'Egypt', 'passport_expiry' => now()->addMonths(4)->toDateString()]);
    Person::factory()->create(['association_id' => $this->b->id, 'first_name' => 'Badr', 'last_name' => 'Beta', 'photo_path' => 'photos/badr.jpg',
        'dob' => null, 'passport_expiry' => now()->addMonths(3)->toDateString()]);
    Person::factory()->create(['association_id' => $this->a->id, 'first_name' => 'Ina', 'last_name' => 'Ctive', 'is_active' => false,
        'passport_expiry' => now()->addMonth()->toDateString()]);
});

function generalAdmin(array $set = [])
{
    $page = livewire(Dashboard::class)->set('tab', 'general');
    foreach ($set as $k => $v) {
        $page->set($k, $v);
    }

    return $page;
}

it('switches between the Games and the General dashboard tabs', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(Dashboard::class)->assertSee(['Games dashboard', 'General dashboard', 'Registry status'])->assertDontSee('Age groups')
        ->set('tab', 'general')->assertSee(['Registry', 'Gender', 'Age groups', 'Nationalities', 'Athletes by sport', 'Registry by association', 'Passports expiring soon'])
        ->assertDontSee('Registry status');
    // Remembered in the query string.
    Livewire::withQueryParams(['tab' => 'general'])->test(Dashboard::class)->assertSee('Age groups');
});

it('counts every association for STA and narrows to the chosen association and type, active people only', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    $p = generalAdmin()->assertSee(['All associations', 'Counts include active people only.', 'ALP', 'BET', 'Amal Alpha', 'Badr Beta'])
        ->assertDontSee('Ina Ctive')->instance()->general()->people();
    expect($p)->toMatchArray(['total' => 3, 'athletes' => 2, 'officials' => 1, 'expiring' => 3]);

    $p = generalAdmin(['genAssociation' => $this->a->id])->assertDontSee('Badr Beta')->instance()->general()->people();
    expect($p)->toMatchArray(['total' => 2, 'expiring' => 2])->and($p['perAssociation']->pluck('code')->all())->toBe(['ALP']);

    expect(generalAdmin(['genType' => 'official'])->instance()->general()->people())->toMatchArray(['total' => 1, 'athletes' => 0, 'officials' => 1]);
});

it('counts gender and age groups, and Unknown only for people without a date of birth', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Person::factory()->create(['association_id' => $this->b->id, 'dob' => now()->subYears(26)->toDateString()]);
    Person::factory()->create(['association_id' => $this->b->id, 'dob' => now()->subYears(51)->toDateString()]);

    $p = generalAdmin()->assertSee('Unknown')->instance()->general()->people();
    expect($p)->toMatchArray(['male' => 4, 'female' => 1, 'unknown' => 1])
        ->and($p['ages'])->toBe(['u18' => 1, 'a18' => 0, 'a26' => 1, 'a36' => 1, 'a50' => 1]);

    Person::query()->whereNull('dob')->update(['dob' => now()->subYears(20)->toDateString()]);
    generalAdmin()->assertDontSee('Unknown');
});

it('shows up to 8 people in the photo cluster and +N for the rest, linking to the registry', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Person::factory()->count(7)->create(['association_id' => $this->b->id]);

    $page = generalAdmin();
    expect($page->instance()->general()->cluster())->toHaveCount(8)
        ->and($page->instance()->general()->cluster()->take(2)->pluck('photo_path')->filter())->toHaveCount(2); // photos first
    $page->assertSeeHtml('aria-label="2 more people in the registry"')->assertSee('+2');
});

it('scopes the portal General dashboard to the user’s own association', function () {
    actingInPortal(User::factory()->assocEditor($this->a)->create());

    $page = livewire(Home::class)->assertSee(['Games dashboard', 'General dashboard'])->set('tab', 'general')
        ->assertSee(['Amal Alpha', 'Omar Alpha', 'Your entries'])->assertDontSee(['Badr Beta', 'Ina Ctive', 'All associations', 'Registry by association', 'BET'])
        ->assertDontSeeHtml('badr.jpg');
    // A crafted association id is ignored: the tenant always wins.
    $page->set('genAssociation', $this->b->id)->assertDontSee('Badr Beta');
    $d = $page->instance()->general();
    expect($d->people())->toMatchArray(['total' => 2, 'expiring' => 2])
        ->and($d->cluster()->pluck('association_id')->unique()->all())->toBe([$this->a->id])
        ->and($d->expiring()->pluck('association_id')->unique()->all())->toBe([$this->a->id]);
});

it('hides the people cards from STA without People access and shows the game cards with Submissions', function () {
    actingInAdmin(grantSta(User::factory()->sta()->create(), StaPermission::ViewSubmissions));

    generalAdmin()->assertSee(['Athletes by sport', 'Active game'])
        ->assertDontSee(['Age groups', 'Nationalities', 'Passports expiring soon', 'Registry by association', 'Amal Alpha']);
});

it('never shows medical data', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Person::query()->each(fn (Person $p) => $p->update(['allergies' => 'Peanuts', 'medication' => 'Insulin', 'chronic_conditions' => 'Asthma']));

    generalAdmin()->assertDontSee(['Peanuts', 'Insulin', 'Asthma', 'Allergies', 'Blood group']);
});

it('counts distinct Long List athletes per sport and the open phase progress of the chosen game', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $game = Game::factory()->create(['name' => 'Gulf Games']);
    $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(4)->addHour()]);
    $game->associations()->attach([$this->a->id, $this->b->id]);
    $gs = GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => Sport::factory()->create(['association_id' => $this->a->id, 'name' => 'Judo'])->id]);
    [$e1, $e2] = GameEvent::factory()->count(2)->create(['game_id' => $game->id, 'game_sport_id' => $gs->id]);
    $s = Submission::for($game, $this->a, 3);
    $amal = Person::query()->where('first_name', 'Amal')->first();
    foreach ([$e1, $e2] as $e) {
        $s->entries()->create(['person_id' => $amal->id, 'game_event_id' => $e->id, 'game_sport_id' => $gs->id]); // one athlete, two events
    }
    $s->entries()->create(['person_id' => Person::query()->where('type', PersonType::Official)->first()->id, 'game_event_id' => null, 'game_sport_id' => $gs->id]);

    $page = generalAdmin()->assertSee(['Gulf Games', 'Judo', 'Phase 3 · Long List', '4 days left', 'Submitted', 'Not started', 'Open game dashboard']);
    expect($page->instance()->general()->athletesBySport()->all())->toBe(['Judo' => 1])
        ->and($page->instance()->general()->progress()['counts'])->toMatchArray(['submitted' => 0, 'draft' => 1, 'no_response' => 1]);

    actingInPortal(User::factory()->assocEditor($this->b)->create());
    expect(livewire(Home::class)->set('tab', 'general')->assertSee(['Gulf Games', 'Phase 3 · Long List'])->instance()->general()->athletesBySport()->all())->toBe([]);
});
