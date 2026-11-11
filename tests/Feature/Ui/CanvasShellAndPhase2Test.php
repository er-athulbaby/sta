<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Filament\Portal\Pages\Phase2Entry;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\NumberEntry;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

// Canvas exactness (Task 8b-1): Main.dc.html / Phase2.dc.html shell and Phase 2, plus Phase 1 and the portal Games page.

beforeEach(function () {
    Notification::fake();
    $this->assoc = Association::factory()->create(['name' => 'Bahrain Weightlifting Federation', 'code' => 'BWF']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->associations()->attach($this->assoc);
    $this->gs = GameSport::factory()->create(['game_id' => $this->game->id, 'p2_max_men' => 4, 'p2_max_women' => 4, 'p2_max_total' => 8,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Weightlifting'])->id]);
    $this->ev = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->gs->id, 'p2_max_athletes' => 2,
        'event_id' => Event::factory()->create(['sport_id' => $this->gs->sport_id, 'name' => '60kg', 'gender' => EventGender::Men])->id]);
});

it('draws the admin menu like Main.dc.html: brand in the menu, sentence-case items, user block with role', function () {
    actingInAdmin(User::factory()->superAdmin()->create(['name' => 'Ali Haroona']));

    $html = $this->get('/admin')->assertOk()->getContent();

    expect($html)->toMatch('/class="fi-sidebar-header".*?Bahrain Olympic Committee.*?Sports Entry.*?Admin panel/s')
        ->toContain('Extra fields')->not->toContain('Extra Fields')
        ->not->toContain('fi-global-search')
        ->toMatch('/fi-sidebar-footer.*?sta-user-role">Super Admin<.*?fi-user-menu-trigger.*?Ali Haroona/s');
    $this->get('/admin')->assertSeeInOrder(['Dashboard', 'Games', 'Associations', 'Sports &amp; events', 'Clothing items', 'People',
        'Reports', 'Audit log', 'STA users', 'Settings', 'Extra fields'], escape: false);
});

it('draws the portal menu like Phase2.dc.html: brand, association block, items, user block', function () {
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create(['name' => 'Mariam Khalil']));

    $html = $this->get("/portal/{$this->assoc->id}")->assertOk()->getContent();

    expect($html)->toMatch('/class="fi-sidebar-header".*?Association portal.*?sta-assoc-block.*?Bahrain Weightlifting Federation.*?BWF/s')
        ->not->toContain('fi-tenant-menu')
        ->toContain('sta-user-role">Association Admin<');
    $this->get("/portal/{$this->assoc->id}")->assertSeeInOrder(['Home', 'Games', 'Athletes &amp; officials', 'Excel import', 'Team', 'Association profile'], escape: false);
});

it('lays out Phase 2 in the Phase2.dc.html order and wording', function () {
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(3)->addHour()]);
    User::factory()->assocAdmin($this->assoc)->create(['name' => 'Ahmed Saleh']);
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->assertSeeInOrder(['Games', 'Asian Indoor Games', 'Entry by Number', 'Phase 2 of 4 · Open', '2 · Entry by Number',
            'Deadline', '3 days left', 'Reminder emails go out automatically until you submit.',
            'Weightlifting', 'Enter how many athletes you plan to send to each event',
            'Total number of participants', 'Different athletes — one athlete can enter several events', 'Male', 'Female', 'Total',
            'Event', 'Male', 'Female', '60kg', 'Up to 2 athletes', 'Fewer athletes in 60kg', 'More athletes in 60kg', 'Event entries',
            'Expected medals', 'Optional · not linked to an athlete or event', 'Gold', 'Silver', 'Bronze',
            'Weightlifting limits', 'Checked against the total number of participants', 'Male participants', 'Female participants', 'Total participants',
            'Entry policy', 'Each event shows how many athletes it allows.',
            'Save as draft', 'Submit to association admin', 'A saved draft is never submitted.', 'Ahmed Saleh (association admin)', 'Entry by Event PDF'])
        ->assertDontSee('Draft · saved');
});

it('steps event counts with the −/+ buttons inside the event maximum', function () {
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $id = $this->ev->id;

    $page = livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->call('stepCount', $id, -1)->assertSet("counts.{$id}", 0)
        ->call('stepCount', $id, 1)->call('stepCount', $id, 1)->assertSet("counts.{$id}", 2);
    expect($page->html())->toMatch('/aria-label="More athletes in 60kg"[^>]*disabled/');
    $page->call('save')->assertHasNoErrors();
    expect(NumberEntry::where('game_event_id', $id)->value('count'))->toBe(2);
});

it('does not step a read-only Phase 2', function () {
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'deadline' => now()->subDay()]);
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(Phase2Entry::class, ['game' => $this->game->id])->call('stepCount', $this->ev->id, 1)
        ->assertSet("counts.{$this->ev->id}", null);
});

it('builds Phase 1 in the Phase 2 structure: header band, banner, main card, submit block', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(6)->addHour()]);
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->assertSeeInOrder(['Entry by Sport', 'Phase 1 of 4 · Open', 'Draft · saved', '1 · Entry by Sport', 'Deadline', '6 days left',
            'Sports you were invited to', 'Weightlifting', 'Yes', 'No', 'Maybe', 'Save as draft', 'Send for approval', 'Entry by Sport PDF']);
});

it('shows each game as Main.dc.html phase cards with the canvas status wording', function () {
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(6)->addHour()]);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Draft]);
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(Games::class)->assertSeeHtml('sta-canvas-page')
        ->assertSeeInOrder(['Asian Indoor Games', 'Phase 2', 'No response', 'Entry by Number', 'Open · Deadline', '6 days left',
            'Phase 3', 'Long List', 'Not opened']);
});
