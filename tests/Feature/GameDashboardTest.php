<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\DeadlineChanged;
use App\Notifications\PhaseReminder;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    $this->game = Game::factory()->create();
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(3)]);
    $this->done = Association::factory()->create(['name' => 'Done Fed', 'code' => 'DON']);
    $this->late = Association::factory()->create(['name' => 'Late Fed', 'code' => 'LAT']);
    $this->game->associations()->attach([$this->done->id, $this->late->id]);
    Submission::for($this->game, $this->done, 2)->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'DON-G001-P2-R1']);
    $this->lateAdmin = User::factory()->assocAdmin($this->late)->create();
    actingInAdmin(User::factory()->superAdmin()->create());
});

it('shows the status matrix', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSee('Done Fed')->assertSee('Submitted')->assertSee('Late Fed')->assertSee('No response');
    expect(Submission::withoutGlobalScopes()->where('association_id', $this->late->id)->count())->toBe(0);
});

it('hides fully submitted rows when pending only', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->set('show', 'pending')->assertDontSee('Done Fed')->assertSee('Late Fed');
});

it('reminds only pending associations', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->callAction('remindPending', ['phase' => 2]);
    Notification::assertSentTo($this->lateAdmin, PhaseReminder::class);
    Notification::assertSentTimes(PhaseReminder::class, 1);
});

it('extends and re-opens for one association', function () {
    $page = livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()]);
    $page->call('extend', $this->late->id, 2, now()->addDays(10)->format('Y-m-d H:i'));
    expect(Submission::withoutGlobalScopes()->where('association_id', $this->late->id)->sole()->deadline_override)->not->toBeNull();

    $page->call('reopen', $this->done->id, 2);
    expect(Submission::withoutGlobalScopes()->where('association_id', $this->done->id)->sole()->status)->toBe(SubmissionStatus::Draft);
    Notification::assertSentTo($this->lateAdmin, DeadlineChanged::class);
});

it('shows workflow errors as a notification instead of failing', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->call('extend', $this->done->id, 2, now()->addDays(10)->format('Y-m-d H:i'))
        ->assertNotified();
});

it('needs view_submissions to see and manage_games to extend', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($sta);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])->assertOk()
        ->call('extend', $this->late->id, 2, now()->addDays(10)->format('Y-m-d H:i'))->assertForbidden();
});

it('lets a view-only STA see the list and dashboard action but not create or edit', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($sta);
    livewire(ListGames::class)->assertSee($this->game->name)
        ->assertTableActionVisible('dashboard', $this->game)->assertTableActionHidden('edit', $this->game);
    $this->get(GameResource::getUrl('dashboard', ['record' => $this->game]))->assertOk();
    $this->get(GameResource::getUrl('create'))->assertForbidden();
    $this->get(GameResource::getUrl('edit', ['record' => $this->game]))->assertForbidden();
});

it('hides the dashboard from a manage-only STA', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManageGames->value);
    actingInAdmin($sta);
    livewire(ListGames::class)->assertTableActionHidden('dashboard', $this->game)->assertTableActionVisible('edit', $this->game);
    $this->get(GameResource::getUrl('dashboard', ['record' => $this->game]))->assertForbidden();
});

it('forbids users without view_submissions', function () {
    actingInAdmin(User::factory()->sta()->create());
    $this->get(GameResource::getUrl('dashboard', ['record' => $this->game]))->assertForbidden();
});

it('refuses reminders without send_reminders and reopen without manage_games', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($sta);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertActionHidden('remindPending')
        ->call('reopen', $this->done->id, 2)->assertForbidden();
});

it('refuses disabled phases and uninvited associations without creating rows', function () {
    $other = Association::factory()->create();
    $d = now()->addDays(10)->format('Y-m-d H:i');
    $mk = fn () => livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()]);
    $mk()->call('extend', $this->late->id, 3, $d)->assertNotFound();
    $mk()->call('reopen', $this->late->id, 3)->assertNotFound();
    $mk()->call('extend', $other->id, 2, $d)->assertNotFound();
    expect(Submission::withoutGlobalScopes()->where('association_id', '!=', $this->done->id)->count())->toBe(0);
});

it('rejects a bad or past extend deadline', function () {
    $page = livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()]);
    $page->call('extend', $this->late->id, 2, 'garbage')->assertNotified('Give a valid deadline in the future.');
    $page->call('extend', $this->late->id, 2, now()->subDay()->format('Y-m-d H:i'))->assertNotified('Give a valid deadline in the future.');
    expect(Submission::withoutGlobalScopes()->where('association_id', $this->late->id)->count())->toBe(0);
});
