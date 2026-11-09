<?php

use App\Enums\StaPermission;
use App\Filament\Resources\People\Pages\ListPeople;
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

it('keeps every medical-gated value out of the audit log', function () {
    $p = Person::factory()->official()->create(['meal_requirement' => 'Vegan', 'medical_clearance_expiry' => '2027-01-01']);
    Person::query()->findOrFail($p->id)->update(['meal_requirement' => 'Halal', 'medical_clearance_expiry' => '2028-02-02']);
    $log = Activity::where('subject_type', Person::class)->where('event', 'updated')->latest('id')->first();

    expect(json_encode($log->properties))->not->toContain('Halal')->not->toContain('Vegan')->not->toContain('2028-02-02')->not->toContain('2027-01-01')
        ->and($log->properties['medical_changed'] ?? null)->toBeTrue();
});

it('has no medical_changed marker when nothing medical changed', function () {
    $p = Person::factory()->create();
    Person::query()->findOrFail($p->id)->update(['first_name' => 'Renamed']);
    $log = Activity::where('subject_type', Person::class)->where('event', 'updated')->latest('id')->first();

    expect($log->properties->has('medical_changed'))->toBeFalse();
});

it('denies medical access to nobody', function () {
    expect(Person::canViewMedical(null))->toBeFalse();
});

it('hides the WADA certificate link from STA without view_medical', function () {
    $person = Person::factory()->create(['wada_certificate_path' => 'wada/x.pdf']);
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManagePeople->value);
    actingInAdmin($sta);
    Livewire\Livewire::test(ListPeople::class)->assertTableActionHidden('view_wada_certificate_path', $person);

    $sta->givePermissionTo(StaPermission::ViewMedical->value);
    actingInAdmin($sta->fresh());
    Livewire\Livewire::test(ListPeople::class)->assertTableActionVisible('view_wada_certificate_path', $person);
});
