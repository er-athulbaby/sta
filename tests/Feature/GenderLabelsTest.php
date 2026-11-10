<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Filament\Portal\Pages\Phase2Entry;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\RelationManagers\GameSportsRelationManager;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Sport;
use App\Models\User;
use App\Services\Phases\ShortListChecks;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->gs = GameSport::factory()->create(['game_id' => $this->game->id, 'p2_max_men' => 2, 'p2_max_women' => 2,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    // Event names deliberately avoid the words being checked.
    foreach (['-60kg' => EventGender::Men, '-52kg' => EventGender::Women, 'Team' => EventGender::Mixed] as $name => $g) {
        GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->gs->id,
            'event_id' => Event::factory()->create(['sport_id' => $this->gs->sport_id, 'name' => $name, 'gender' => $g])->id]);
    }
});

it('labels event genders Male, Female and Mixed', function () {
    expect(array_map(fn (EventGender $g) => $g->label(), EventGender::cases()))->toBe(['Male', 'Female', 'Mixed']);
});

it('never shows Men or Women on the Phase 2 page', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
    $html = livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->assertSeeHtml('>Male</th>')->assertSeeHtml('>Female</th>')->html();

    expect($html)->not->toMatch('/\b(Men|Women)\b/');
});

it('never shows Men or Women in the Entry by Event PDF', function () {
    $html = view('pdf.entry-by-event', [
        'game' => $this->game, 'association' => $this->assoc, 'gameSport' => $this->gs, 'sportName' => 'Judo',
        'rows' => [['event' => '-60kg', 'gender' => 'men', 'count' => 1], ['event' => '-52kg', 'gender' => 'women', 'count' => 1]],
        'totals' => ['men' => 1, 'women' => 1, 'total' => 2],
        'participants' => ['male' => 1, 'female' => 1], 'medals' => ['gold' => null, 'silver' => null, 'bronze' => null],
        'referenceNo' => 'BJF-G001-P2-R1', 'declaration' => 'I confirm.', 'nocLogo' => null, 'gameLogo' => null,
    ])->render();

    expect($html)->toContain('Male')->toContain('Female')->not->toMatch('/\b(Men|Women)\b/');
});

it('labels admin limits and Short List kinds male and female', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(GameSportsRelationManager::class, ['ownerRecord' => $this->game, 'pageClass' => EditGame::class])
        ->assertSee('P2 male')->assertSee('P2 female')->assertDontSee('P2 men');

    expect(ShortListChecks::describe(['sport' => 'Judo', 'kind' => 'women', 'count' => 3, 'max' => 2]))
        ->toBe('Judo: 3 female athletes (limit 2)');
});
