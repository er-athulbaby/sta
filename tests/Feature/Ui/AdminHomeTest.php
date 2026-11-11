<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\Role;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Associations\AssociationResource;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Invitation;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\PhaseReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(6)->addHour()]);
    $this->assoc = Association::factory()->create(['name' => 'Bahrain Weightlifting Federation', 'code' => 'BWF']);
    $this->game->associations()->attach($this->assoc);
    Person::factory()->create(['association_id' => $this->assoc->id]);
});

it('shows the canvas home sections, in order, to a super admin', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(Dashboard::class)
        ->assertSeeInOrder(['Dashboard', 'all games and associations', 'New game',
            'Active games', 'Asian Indoor Games', 'Phase 3 · Long List', 'Deadline', 'Time left', '6 days',
            'Associations submitted', '0 of 1', '1 not started', 'Open game dashboard',
            'Needs your attention', 'Across the active game · next 7 days', '1 phase deadline this week', 'Open',
            '1 association hasn’t submitted the Long List', '1 not started: Bahrain Weightlifting Federation', 'Remind 1',
            'Registry health', 'Permanent registry of all associations', 'Athletes', 'Team officials', 'Complete', 'Incomplete',
            'By association', 'BWF', 'Incomplete = missing any Long List field',
            'Associations', 'each has 1 admin and up to 2 editors', 'All associations',
            'Association', 'Code', 'Admin', 'Editors', 'People', 'Complete', 'Last activity', 'Active',
            'Bahrain Weightlifting Federation', 'No admin', '0 / 2', 'Showing 1 of 1 · sorted by last activity',
            'Recent activity', 'From the audit log · medical changes are marked, values are never shown', 'Open audit log']);
});

it('hides the sections a user may not see', function () {
    actingInAdmin(User::factory()->sta()->create());

    livewire(Dashboard::class)->assertOk()->assertSee('Dashboard')
        ->assertDontSee('Active games')->assertDontSee('Asian Indoor Games')->assertDontSee('Needs your attention')
        ->assertDontSee('Registry health')->assertDontSee('All associations')->assertDontSee('Recent activity')->assertDontSee('New game');
});

it('hides the registry columns of the associations table without manage_people (ruling U6)', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManageAssociations->value);
    actingInAdmin($sta);

    livewire(Dashboard::class)->assertSee('All associations')->assertSee('Needs your attention')
        ->assertDontSee('Last activity')->assertDontSee('Registry health')->assertDontSee('Active games');
});

it('counts the registry in SQL with the Person::isComplete rule and passports expiring within 6 months', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Person::factory()->create(['association_id' => $this->assoc->id, 'passport_expiry' => now()->addMonths(2)]);
    $done = Person::factory()->official()->complete()->create(['association_id' => $this->assoc->id]);
    $open = Person::factory()->official()->create(['association_id' => $this->assoc->id]);
    expect($done->fresh()->isComplete())->toBeTrue()->and($open->fresh()->isComplete())->toBeFalse();

    $r = livewire(Dashboard::class)->instance()->registry();

    expect($r['athletes'])->toBe(2)->and($r['officials'])->toBe(2)
        ->and($r['complete'])->toBe(Person::withoutGlobalScopes()->get()->filter->isComplete()->count())
        ->and($r['complete'] + $r['incomplete'])->toBe(4)
        ->and($r['expiring'])->toBe(Person::query()->whereNotNull('passport_expiry')->where('passport_expiry', '<', now()->addMonths(6))->count())
        ->and($r['top'][0])->toMatchArray(['code' => 'BWF', 'total' => 4]);
});

it('lists pending invitations in the attention list', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Invitation::create(['association_id' => $this->assoc->id, 'email' => 'a@x.test', 'role' => Role::AssocAdmin,
        'token_hash' => 'x', 'expires_at' => now()->addDays(3)]);

    livewire(Dashboard::class)->assertSee('1 invitation pending')->assertSee('Bahrain Weightlifting Federation admin')
        ->assertSee('Invite pending')->assertSeeHtml('href="'.AssociationResource::getUrl().'"');
});

it('reminds the associations that have not submitted, from "Remind N"', function () {
    Notification::fake();
    actingInAdmin(User::factory()->superAdmin()->create());
    $done = Association::factory()->create(['code' => 'DON']);
    $this->game->associations()->attach($done);
    Submission::for($this->game, $done, 3)->update(['status' => SubmissionStatus::Submitted]);
    User::factory()->assocAdmin($this->assoc)->create();
    User::factory()->assocAdmin($done)->create();

    livewire(Dashboard::class)->assertSee('Remind 1')
        ->callAction('remind', arguments: ['game' => $this->game->id])->assertNotified('Reminder sent to 1 association');

    Notification::assertSentTimes(PhaseReminder::class, 1);
});

it('refuses the reminder without send_reminders', function () {
    Notification::fake();
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($sta);

    livewire(Dashboard::class)->assertSee('Asian Indoor Games')->assertDontSee('Remind 1')
        ->call('mountAction', 'remind', ['game' => $this->game->id])->callMountedAction();

    Notification::assertNothingSent();
});

it('switches an association active or inactive, only with manage_associations', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    livewire(Dashboard::class)->assertSeeHtml('aria-label="Association BWF active"')->call('toggleActive', $this->assoc->id);
    expect($this->assoc->fresh()->is_active)->toBeFalse();

    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($sta);
    livewire(Dashboard::class)->call('toggleActive', $this->assoc->id)->assertForbidden();
    expect($this->assoc->fresh()->is_active)->toBeFalse();
});

it('shows recent activity with who, role and the record, never the values', function () {
    $admin = actingInAdmin(User::factory()->superAdmin()->create(['name' => 'Ali Haroona']));
    $p = Person::withoutGlobalScopes()->first();
    activity()->performedOn($p)->causedBy($admin)->event('updated')->withProperties(['medical_changed' => true])->log('updated');

    livewire(Dashboard::class)->assertSeeInOrder(['Recent activity', 'Ali Haroona', '(STA Super Admin)', 'updated '.$p->code, 'Medical data changed']);
});

it('renders with the same number of queries for any number of games and associations', function () {
    $admin = User::factory()->superAdmin()->create();
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });
    $count = function (int $games, int $assocs) use (&$queries, $admin) {
        DB::table('activity_log')->delete();
        foreach (range(1, $games) as $g) {
            $game = Game::factory()->create();
            $game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Closed]);
            $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(3)]);
            foreach (range(1, $assocs) as $i) {
                homeFixture($game, 'G'.$game->id.'A'.$i, 1, 2);
            }
        }
        actingInAdmin($admin);
        $queries = 0;
        livewire(Dashboard::class)->assertSee('Needs your attention')->assertSee('Allowed but flagged')->assertSee('from Phase 2');

        return $queries;
    };
    $count(1, 1); // warm-up: permission and settings caches
    $small = $count(1, 2);
    expect($count(3, 5))->toBe($small);
});

/** An association in $game with a submitted Phase 2 number and $entries people on its Long List and Short List. */
function homeFixture(Game $game, string $code, int $p2, int $entries): Association
{
    $a = Association::factory()->create(['name' => $code.' Fed', 'code' => $code]);
    $game->associations()->attach($a);
    $gs = GameSport::factory()->create(['game_id' => $game->id, 'sport_id' => Sport::factory()->create(['association_id' => $a->id])->id]);
    $ev = GameEvent::factory()->create(['game_id' => $game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => '-60kg', 'gender' => EventGender::Men])->id]);
    $s2 = Submission::for($game, $a, 2);
    $s2->update(['status' => SubmissionStatus::Submitted]);
    NumberEntry::create(['submission_id' => $s2->id, 'game_event_id' => $ev->id, 'count' => $p2]);
    $people = Person::factory()->count($entries)->create(['association_id' => $a->id]);
    $s = Submission::for($game, $a, 3);
    foreach ($people as $p) {
        $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $ev->id, 'game_sport_id' => $gs->id]);
    }

    return $a;
}
