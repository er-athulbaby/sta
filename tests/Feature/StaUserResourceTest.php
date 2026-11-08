<?php

use App\Enums\Role;
use App\Enums\StaPermission;
use App\Filament\Resources\StaUsers\Pages\CreateStaUser;
use App\Models\User;
use function Pest\Livewire\livewire;

it('lets super admin create an STA user with permissions', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    $permissionIds = \Spatie\Permission\Models\Permission::whereIn('name', [
        StaPermission::ManageAssociations->value, StaPermission::ViewReports->value,
    ])->pluck('id')->all();

    livewire(CreateStaUser::class)
        ->fillForm(['name' => 'Fatima', 'email' => 'fatima@sta.bh', 'password' => 'a-long-password-1', 'is_active' => true, 'permissions' => $permissionIds])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'fatima@sta.bh')->first();
    expect($user->role)->toBe(Role::Sta)
        ->and($user->association_id)->toBeNull()
        ->and($user->can(StaPermission::ManageAssociations->value))->toBeTrue()
        ->and($user->can(StaPermission::ManageGames->value))->toBeFalse();
});

it('hides STA user management from STA users', function () {
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::cases());
    $this->actingAs($sta)->get('/admin/sta-users')->assertForbidden();
});

it('gives super admin every permission', function () {
    expect(User::factory()->superAdmin()->create()->can(StaPermission::ViewAuditLog->value))->toBeTrue();
});
