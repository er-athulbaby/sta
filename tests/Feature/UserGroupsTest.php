<?php

use App\Enums\PersonType;
use App\Enums\Role;
use App\Enums\StaAction;
use App\Enums\StaArea;
use App\Enums\StaPermission;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Associations\Pages\ListAssociations;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Pages\GameExportFiles;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Filament\Resources\People\PersonResource;
use App\Filament\Resources\StaGroups\Pages\CreateStaGroup;
use App\Filament\Resources\StaGroups\Pages\EditStaGroup;
use App\Filament\Resources\StaGroups\Pages\ListStaGroups;
use App\Models\Association;
use App\Models\Game;
use App\Models\Person;
use App\Models\StaGroup;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Livewire\livewire;

function readOnlyUser(): User
{
    $u = User::factory()->sta()->create();
    $u->syncRoles([StaGroup::findByName(StaGroup::READ_ONLY)]);

    return $u;
}

it('saves the grid of a new group and loads it back', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(CreateStaGroup::class)
        ->fillForm(['name' => 'Games desk', 'description' => 'Runs the games', 'cells' => ['games.access', 'games.save', 'reports.print', 'medical.access', 'nope.access']])
        ->call('create')->assertHasNoFormErrors();

    $group = StaGroup::findByName('Games desk');
    // Save and Print imply Access; unknown cells are dropped.
    expect($group->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['games.access', 'games.save', 'medical.access', 'reports.access', 'reports.print'])
        ->and($group->description)->toBe('Runs the games');

    $page = livewire(EditStaGroup::class, ['record' => $group->getRouteKey()]);
    expect(collect($page->get('data.cells'))->sort()->values()->all())
        ->toBe(['games.access', 'games.save', 'medical.access', 'reports.access', 'reports.print']);
    $page->fillForm(['cells' => ['people.delete']])
        ->call('save')->assertHasNoFormErrors();
    expect($group->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['people.access', 'people.delete']);
});

it('normalises the grid like its row and column boxes: New/Save/Delete/Print imply Access', function () {
    $column = collect(StaArea::cases())->filter(fn (StaArea $a) => in_array(StaAction::Print, $a->actions(), true))
        ->map(fn (StaArea $a) => $a->permission(StaAction::Print))->all();
    expect(StaArea::normalise($column))->toBe(StaArea::normalise([...$column, 'submissions.access', 'people.access', 'reports.access']))
        ->and(StaArea::normalise($column))->toContain('people.access', 'game_system_files.print')->not->toContain('game_system_files.access')
        ->and(StaArea::normalise(['edit_as_sta.save']))->toBe(['edit_as_sta.save'])
        ->and(StaArea::normalise(['games.delete', 'sports.delete']))->toBe([]); // cells that do not exist
});

it('draws the grid with labelled cells and blanks where a cell does not apply', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(CreateStaGroup::class)->assertOk()
        ->assertSeeHtml('aria-label="Games (setup, phases, limits): Save"')
        ->assertDontSeeHtml('aria-label="Games (setup, phases, limits): Delete"')
        ->assertSeeHtml('aria-label="All rows: Print"')
        ->assertSeeHtml('aria-label="Edit as STA: Save"')
        ->assertDontSeeHtml('aria-label="Edit as STA: Access"')
        ->assertSee('Special rights');
});

it('lists the groups with the Users & groups tabs', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(ListStaGroups::class)->assertCanSeeTableRecords(StaGroup::all())
        ->assertSee(StaGroup::FULL_ACCESS)->assertSee(StaGroup::READ_ONLY)->assertSee('New group')->assertSee('Users');
});

it('audits group changes', function () {
    $admin = actingInAdmin(User::factory()->superAdmin()->create());

    livewire(CreateStaGroup::class)->fillForm(['name' => 'Audit', 'cells' => ['audit_log.access']])->call('create')->assertHasNoFormErrors();
    $group = StaGroup::findByName('Audit');
    livewire(EditStaGroup::class, ['record' => $group->getRouteKey()])->fillForm(['name' => 'Audit desk', 'cells' => ['audit_log.access', 'users.access']])
        ->call('save')->assertHasNoFormErrors();

    $logs = Activity::where('subject_type', $group->getMorphClass())->where('subject_id', $group->id)->orderBy('id')->get();
    expect($logs->pluck('description')->all())->toBe(['created', 'group permissions updated', 'updated', 'group permissions updated'])
        ->and($logs[3]->properties['permissions'])->toBe(['audit_log.access', 'users.access'])
        ->and($logs[3]->causer_id)->toBe($admin->id);
});

it('does not delete a group that still has users', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $user = readOnlyUser();
    $group = StaGroup::findByName(StaGroup::READ_ONLY);

    livewire(EditStaGroup::class, ['record' => $group->getRouteKey()])->callAction('delete');
    expect(StaGroup::find($group->id))->not->toBeNull();

    $user->syncRoles([]);
    livewire(EditStaGroup::class, ['record' => $group->getRouteKey()])->callAction('delete');
    expect(StaGroup::find($group->id))->toBeNull();
});

it('needs Users & groups: New / Save / Delete for the group screens', function () {
    $u = User::factory()->sta()->create();
    $u->givePermissionTo(['users.access']);
    actingInAdmin($u);
    $group = StaGroup::findByName(StaGroup::READ_ONLY);

    $this->get('/admin/user-groups')->assertOk();
    $this->get('/admin/user-groups/create')->assertForbidden();
    $this->get("/admin/user-groups/{$group->id}/edit")->assertForbidden();

    $u->givePermissionTo('users.save');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    livewire(EditStaGroup::class, ['record' => $group->getRouteKey()])->assertActionHidden('delete');
});

it('lets a Read only user view but not create, save, delete or export', function () {
    actingInAdmin(readOnlyUser());
    $game = Game::factory()->create(['published_at' => now()]);
    $a = Association::factory()->create();
    $person = Person::factory()->create(['association_id' => $a->id]);

    // Games: the list opens; New and every setup screen (Save) do not.
    $this->get('/admin/games')->assertOk();
    expect(GameResource::canCreate())->toBeFalse()->and(GameResource::canEdit($game))->toBeFalse();
    $this->get('/admin/games/create')->assertForbidden();
    $this->get("/admin/games/{$game->id}/edit")->assertForbidden();
    $this->get("/admin/games/{$game->id}/setup/limits")->assertForbidden();

    // People: list yes; add, edit, delete and Excel export no.
    $this->get('/admin/people')->assertOk();
    $this->get('/admin/people/create')->assertForbidden();
    $this->get("/admin/people/{$person->id}/edit")->assertForbidden();
    expect(PersonResource::canDelete($person))->toBeFalse();
    livewire(ListPeople::class)->assertDontSee('Export Excel')->call('exportExcel')->assertForbidden();

    // Reports: the page opens; exports do not run.
    livewire(Reports::class)->assertOk()->call('export')->assertForbidden();

    // Game system files: the layouts page opens, but nothing with passport numbers downloads and layouts are not saved.
    livewire(GameExportFiles::class, ['record' => $game->getRouteKey()])->assertOk()
        ->assertActionHidden('download')->assertActionHidden('zip')->assertActionHidden('copy')
        ->call('download', PersonType::Athlete)->assertForbidden();
    livewire(GameExportFiles::class, ['record' => $game->getRouteKey()])->call('save')->assertForbidden();

    // Medical data and the special rights stay off.
    expect(Person::canViewMedical(auth()->user()))->toBeFalse()
        ->and(StaArea::Reminders->can())->toBeFalse()
        ->and(StaArea::EditAsSta->can(StaAction::Save))->toBeFalse();
    $this->get('/admin/activity-logs')->assertForbidden();
    $this->get('/admin/sta-users')->assertForbidden();
});

it('lets the Full access group do everything', function () {
    $u = User::factory()->sta()->create();
    $u->syncRoles([StaGroup::findByName(StaGroup::FULL_ACCESS)]);

    foreach (StaArea::allPermissions() as $p) {
        expect($u->can($p))->toBeTrue();
    }
});

it('keeps association users out of groups and the admin panel', function () {
    $assocAdmin = User::factory()->assocAdmin()->create();

    expect($assocAdmin->roles)->toBeEmpty()
        ->and(StaArea::Games->can(user: $assocAdmin))->toBeFalse()
        ->and(Person::canViewMedical($assocAdmin))->toBeTrue(); // their own registry, unchanged
    $this->actingAs($assocAdmin)->get('/admin')->assertRedirect(); // sent to their portal
    $this->actingAs($assocAdmin)->get('/admin/games')->assertRedirect();
});

it('migrates every old right to a group with the same access', function () {
    $migration = require database_path('migrations/2026_11_25_000100_create_sta_user_groups.php');
    $migration->down();
    $registrar = app(PermissionRegistrar::class);
    $registrar->forgetCachedPermissions();

    // One STA user per old right, one with a mix, one with none, two sharing a mix, plus a Super Admin and an association admin.
    $single = collect(StaPermission::cases())->mapWithKeys(fn (StaPermission $p) => [$p->value => tap(User::factory()->sta()->create())->givePermissionTo($p->value)]);
    $mix = [StaPermission::ViewSubmissions->value, StaPermission::EditSubmissions->value, StaPermission::ViewReports->value];
    $mixed = User::factory()->sta()->count(2)->create()->each->givePermissionTo($mix);
    $none = User::factory()->sta()->create();
    $super = User::factory()->superAdmin()->create();
    $assoc = User::factory()->assocAdmin()->create();

    $migration->up();
    $registrar->forgetCachedPermissions();

    foreach ($single as $right => $user) {
        $user = $user->fresh();
        expect($user->roles)->toHaveCount(1);
        foreach (StaPermission::from($right)->grants() as $p) {
            expect($user->can($p))->toBeTrue("{$right} lost {$p}");
        }
        expect($user->can('dashboard.access'))->toBeTrue()
            ->and($user->can('users.access'))->toBeFalse()
            ->and($user->can('people.delete'))->toBeFalse(); // deleting people stays Super Admin only
    }
    // Same combination = same group.
    expect($mixed[0]->fresh()->roles->pluck('id')->all())->toBe($mixed[1]->fresh()->roles->pluck('id')->all())
        ->and($mixed[0]->fresh()->can('edit_as_sta.save'))->toBeTrue()
        ->and($mixed[0]->fresh()->can('reports.delete'))->toBeTrue()
        ->and($mixed[0]->fresh()->can('people.access'))->toBeFalse()
        ->and($none->fresh()->roles)->toHaveCount(1)
        ->and($none->fresh()->can('dashboard.access'))->toBeTrue()
        ->and($none->fresh()->can('games.access'))->toBeFalse()
        ->and($super->fresh()->roles)->toBeEmpty()
        ->and($super->fresh()->can('users.delete'))->toBeTrue()
        ->and($assoc->fresh()->roles)->toBeEmpty();

    // 11 singles + the mix + none = 13 migrated groups, plus Full access and Read only; old rights are gone.
    expect(StaGroup::where('name', 'like', 'Migrated group %')->count())->toBe(13)
        ->and(StaGroup::whereIn('name', [StaGroup::FULL_ACCESS, StaGroup::READ_ONLY])->count())->toBe(2)
        ->and(Permission::whereIn('name', array_map(fn ($p) => $p->value, StaPermission::cases()))->count())->toBe(0)
        ->and(StaGroup::where('name', 'Migrated group 1')->value('description'))->toStartWith('Same access as before user groups:');
});

it('redirects a user without Dashboard to the first page they may open', function () {
    $u = User::factory()->sta()->create();
    $u->givePermissionTo('people.access');
    actingInAdmin($u);

    livewire(Dashboard::class)->assertRedirect(PersonResource::getUrl());
});

it('enforces Associations: New / Save / Delete on the invitation and user actions', function () {
    $u = User::factory()->sta()->create();
    $u->givePermissionTo('associations.access');
    actingInAdmin($u);
    $a = Association::factory()->create();
    $assocUser = User::factory()->assocEditor($a)->create();

    livewire(ListAssociations::class)
        ->assertTableActionHidden('invite', $a)
        ->assertActionHidden('deactivateUser', ['id' => $assocUser->id])
        ->assertDontSee('Invite admin');
    expect(Role::AssocEditor)->toBe($assocUser->role);
});
