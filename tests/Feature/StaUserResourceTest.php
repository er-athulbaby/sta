<?php

use App\Enums\Role;
use App\Enums\StaAction;
use App\Enums\StaArea;
use App\Enums\StaPermission;
use App\Filament\Resources\StaUsers\Pages\CreateStaUser;
use App\Filament\Resources\StaUsers\Pages\EditStaUser;
use App\Filament\Resources\StaUsers\Pages\ListStaUsers;
use App\Filament\Resources\StaUsers\StaUserResource;
use App\Models\StaGroup;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

function staGroup(string $name, array $cells): StaGroup
{
    $g = StaGroup::create(['name' => $name, 'guard_name' => 'web']);
    $g->syncPermissions(StaArea::normalise($cells));

    return $g;
}

it('lets super admin create an STA user in a group', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $group = staGroup('Associations desk', StaPermission::ManageAssociations->grants());

    livewire(CreateStaUser::class)
        ->fillForm(['name' => 'Fatima', 'email' => 'fatima@sta.bh', 'password' => 'a-long-password-1', 'is_active' => true, 'group_id' => $group->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'fatima@sta.bh')->first();
    expect($user->role)->toBe(Role::Sta)
        ->and($user->association_id)->toBeNull()
        ->and($user->roles->pluck('name')->all())->toBe(['Associations desk'])
        ->and(StaArea::Associations->can(StaAction::Save, $user))->toBeTrue()
        ->and(StaArea::Games->can(StaAction::Access, $user))->toBeFalse();
});

it('requires a group for an STA user', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(CreateStaUser::class)
        ->fillForm(['name' => 'Fatima', 'email' => 'fatima@sta.bh', 'password' => 'a-long-password-1', 'is_active' => true, 'group_id' => null])
        ->call('create')
        ->assertHasFormErrors(['group_id' => 'required']);
});

it('hides user management from STA users without Users & groups', function () {
    $sta = grantSta(User::factory()->sta()->create(), ...StaPermission::cases());
    $this->actingAs($sta)->get('/admin/sta-users')->assertForbidden();
    $this->actingAs($sta)->get('/admin/user-groups')->assertForbidden();
});

it('gives super admin every permission', function () {
    $admin = User::factory()->superAdmin()->create();
    foreach (StaArea::allPermissions() as $p) {
        expect($admin->can($p))->toBeTrue();
    }
});

it('offers no hard delete on the STA user edit page', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $sta = User::factory()->sta()->create();

    livewire(EditStaUser::class, ['record' => $sta->getRouteKey()])->assertActionDoesNotExist('delete');
});

it('shows each user\'s group in the users list', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $sta = User::factory()->sta()->create();
    $sta->syncRoles([StaGroup::findByName(StaGroup::READ_ONLY)]);

    livewire(ListStaUsers::class)->assertCanSeeTableRecords([$sta])->assertSee('Read only')->assertSee('Super Admin (everything)')
        ->assertSee('Groups')->assertSee('New user');
});

it('audits group changes of a user on create and edit', function () {
    $admin = actingInAdmin(User::factory()->superAdmin()->create());
    $readOnly = StaGroup::findByName(StaGroup::READ_ONLY);
    $full = StaGroup::findByName(StaGroup::FULL_ACCESS);

    livewire(CreateStaUser::class)
        ->fillForm(['name' => 'Fatima', 'email' => 'fatima@sta.bh', 'password' => 'a-long-password-1', 'is_active' => true, 'group_id' => $readOnly->id])
        ->call('create')->assertHasNoFormErrors();
    $sta = User::firstWhere('email', 'fatima@sta.bh');

    livewire(EditStaUser::class, ['record' => $sta->getRouteKey()])
        ->assertSchemaStateSet(['group_id' => $readOnly->id])
        ->fillForm(['group_id' => $full->id])
        ->call('save')->assertHasNoFormErrors();
    // Saving again without a change logs nothing.
    livewire(EditStaUser::class, ['record' => $sta->getRouteKey()])->call('save')->assertHasNoFormErrors();

    $logs = Activity::where('subject_type', User::class)->where('subject_id', $sta->id)->where('description', 'user group changed')->orderBy('id')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs[0]->properties['attributes']['group'])->toBe(StaGroup::READ_ONLY)
        ->and($logs[1]->properties['old']['group'])->toBe(StaGroup::READ_ONLY)
        ->and($logs[1]->properties['attributes']['group'])->toBe(StaGroup::FULL_ACCESS)
        ->and($logs[1]->causer_id)->toBe($admin->id)
        ->and($sta->fresh()->roles->pluck('name')->all())->toBe([StaGroup::FULL_ACCESS]);
});

it('lets a user with Users & groups manage STA users but never Super Admins', function () {
    $manager = User::factory()->sta()->create();
    $manager->givePermissionTo(StaArea::normalise(['users.new', 'users.save']));
    actingInAdmin($manager);
    $super = User::factory()->superAdmin()->create();
    $sta = User::factory()->sta()->create();

    expect(StaUserResource::canEdit($super))->toBeFalse()
        ->and(StaUserResource::canEdit($sta))->toBeTrue();
    $this->get("/admin/sta-users/{$super->id}/edit")->assertForbidden();

    // No account type field for them: a created user is always an STA user, whatever is posted.
    livewire(CreateStaUser::class)->assertFormFieldIsHidden('role')
        ->fillForm(['name' => 'Ali', 'email' => 'ali@sta.bh', 'password' => 'a-long-password-1', 'is_active' => true,
            'group_id' => StaGroup::findByName(StaGroup::READ_ONLY)->id])
        ->set('data.role', Role::SuperAdmin->value)
        ->call('create')->assertHasNoFormErrors();
    expect(User::firstWhere('email', 'ali@sta.bh')->role)->toBe(Role::Sta);
});

it('never demotes or deactivates the last active Super Admin', function () {
    $admin = actingInAdmin(User::factory()->superAdmin()->create());

    livewire(EditStaUser::class, ['record' => $admin->getRouteKey()])
        ->fillForm(['role' => Role::Sta->value, 'group_id' => StaGroup::findByName(StaGroup::READ_ONLY)->id])
        ->call('save')->assertHasFormErrors(['role']);
    livewire(EditStaUser::class, ['record' => $admin->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')->assertHasFormErrors(['is_active']);
    expect($admin->fresh()->isSuperAdmin())->toBeTrue()->and($admin->fresh()->is_active)->toBeTrue();

    // The model refuses it too, whatever the caller.
    expect(fn () => User::find($admin->id)->update(['role' => Role::Sta]))->toThrow(ValidationException::class);

    // With a second active Super Admin, demoting one works (and needs a group).
    User::factory()->superAdmin()->create();
    livewire(EditStaUser::class, ['record' => $admin->getRouteKey()])
        ->fillForm(['role' => Role::Sta->value, 'group_id' => StaGroup::findByName(StaGroup::READ_ONLY)->id])
        ->call('save')->assertHasNoFormErrors();
    expect($admin->fresh()->role)->toBe(Role::Sta)->and($admin->fresh()->roles->pluck('name')->all())->toBe([StaGroup::READ_ONLY]);
});
