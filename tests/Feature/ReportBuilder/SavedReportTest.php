<?php

use App\Enums\StaPermission;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use Illuminate\Validation\ValidationException;

function reportUser(): User
{
    $user = User::factory()->sta()->create();
    $user->givePermissionTo(StaPermission::ViewReports->value);

    return $user;
}

function savedReport(User $owner, string $visibility, string $name = 'Report'): SavedReport
{
    return SavedReport::create(['name' => $name, 'subject' => 'people', 'definition' => ReportDefinition::fresh(Registry::get('people')),
        'visibility' => $visibility, 'created_by' => $owner->id, 'updated_by' => $owner->id]);
}

it('lets the creator and a super admin change a report; others only see shared ones', function () {
    $owner = reportUser();
    $other = reportUser();
    $admin = User::factory()->superAdmin()->create();
    $private = savedReport($owner, 'private');
    $shared = savedReport($owner, 'shared');

    expect($owner->can('view', $private))->toBeTrue()
        ->and($owner->can('update', $private))->toBeTrue()
        ->and($owner->can('delete', $private))->toBeTrue()
        ->and($owner->can('delete', $shared))->toBeTrue()
        ->and($owner->can('viewAny', SavedReport::class))->toBeTrue()
        ->and($owner->can('create', SavedReport::class))->toBeTrue()
        ->and($other->can('view', $private))->toBeFalse()
        ->and($other->can('update', $private))->toBeFalse()
        ->and($other->can('delete', $private))->toBeFalse()
        ->and($other->can('view', $shared))->toBeTrue()
        ->and($other->can('update', $shared))->toBeFalse()
        ->and($other->can('delete', $shared))->toBeFalse()
        ->and($admin->can('view', $private))->toBeTrue()
        ->and($admin->can('update', $private))->toBeTrue()
        ->and($admin->can('delete', $private))->toBeTrue()
        ->and($admin->can('delete', $shared))->toBeTrue();

    $owner->revokePermissionTo(StaPermission::ViewReports->value);
    $owner = $owner->fresh();
    expect($owner->can('view', $private))->toBeFalse()
        ->and($owner->can('update', $private))->toBeFalse()
        ->and($owner->can('delete', $private))->toBeFalse()
        ->and($owner->can('viewAny', SavedReport::class))->toBeFalse()
        ->and($owner->can('create', SavedReport::class))->toBeFalse()
        ->and(User::factory()->sta()->create()->can('view', $shared))->toBeFalse();
});

it('lists the reports a user may see', function () {
    $owner = reportUser();
    $other = reportUser();
    savedReport($owner, 'private', 'Mine private');
    savedReport($owner, 'shared', 'Mine shared');
    savedReport($other, 'private', 'Theirs private');

    expect(SavedReport::visibleTo($owner)->orderBy('name')->pluck('name')->all())->toBe(['Mine private', 'Mine shared'])
        ->and(SavedReport::visibleTo($other)->orderBy('name')->pluck('name')->all())->toBe(['Mine shared', 'Theirs private'])
        ->and(SavedReport::visibleTo(User::factory()->superAdmin()->create())->count())->toBe(3)
        ->and(SavedReport::visibleTo(User::factory()->sta()->create())->count())->toBe(0);
});

it('splits the list into my reports and shared ones, even for a super admin', function () {
    $owner = reportUser();
    $admin = User::factory()->superAdmin()->create();
    savedReport($owner, 'private', 'Owner private');
    savedReport($owner, 'shared', 'Owner shared');
    savedReport($admin, 'private', 'Admin private');
    savedReport($admin, 'shared', 'Admin shared');

    $names = fn ($query) => $query->orderBy('name')->pluck('name')->all();

    expect($names(SavedReport::visibleTo($admin)->mine($admin)))->toBe(['Admin private', 'Admin shared'])
        ->and($names(SavedReport::visibleTo($admin)->shared()))->toBe(['Admin shared', 'Owner shared'])
        ->and($names(SavedReport::visibleTo($owner)->mine($owner)))->toBe(['Owner private', 'Owner shared'])
        ->and($names(SavedReport::visibleTo($owner)->shared()))->toBe(['Admin shared', 'Owner shared']);
});

it('stores the definition as versioned JSON', function () {
    $report = savedReport(reportUser(), 'private');

    expect($report->fresh()->definition)->toMatchArray(['version' => 1, 'subject' => 'people'])
        ->and($report->creator)->toBeInstanceOf(User::class);
});

it('re-validates the stored definition for the user running it', function () {
    $owner = reportUser();
    $report = savedReport($owner, 'private');

    expect($report->toDefinition($owner))->toBeInstanceOf(ReportDefinition::class);

    $report->update(['definition' => [...$report->definition, 'columns' => ['not_a_field']]]);

    expect(fn () => $report->fresh()->toDefinition($owner))->toThrow(ValidationException::class);
});
