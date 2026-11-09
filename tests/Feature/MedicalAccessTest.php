<?php

use App\Enums\StaPermission;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

it('encrypts medical fields and never logs them', function () {
    $p = Person::factory()->create(['allergies' => 'Peanuts', 'blood_group' => 'O+']);
    $raw = DB::table('people')->where('id', $p->id)->first();
    expect($raw->allergies)->not->toContain('Peanuts');

    Person::query()->findOrFail($p->id)->update(['allergies' => 'Penicillin']);
    $log = Activity::where('subject_type', Person::class)->where('event', 'updated')->latest('id')->first();
    expect(json_encode($log->properties))->not->toContain('Penicillin')->not->toContain('Peanuts')
        ->and($log->properties['medical_changed'] ?? null)->toBeTrue();
});

it('decides who may see medical data', function () {
    $assocUser = User::factory()->assocEditor()->create();
    $sta = User::factory()->sta()->create();
    $medic = User::factory()->sta()->create();
    $medic->givePermissionTo(StaPermission::ViewMedical->value);

    expect(Person::canViewMedical($assocUser))->toBeTrue()
        ->and(Person::canViewMedical($sta))->toBeFalse()
        ->and(Person::canViewMedical($medic))->toBeTrue()
        ->and(Person::canViewMedical(User::factory()->superAdmin()->create()))->toBeTrue();
});
