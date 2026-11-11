<?php

use App\Enums\StaPermission;
use App\Filament\Portal\Resources\People\Pages\CreatePerson;
use App\Filament\Portal\Resources\People\Pages\EditPerson;
use App\Filament\Resources\People\Pages\EditPerson as AdminEditPerson;
use App\Models\Association;
use App\Models\Person;
use App\Models\User;
use Filament\Forms\Components\Field;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BWF']);
});

function v12Athlete(array $o = []): array
{
    return array_merge(['type' => 'athlete', 'first_name' => 'Noor', 'last_name' => 'Alsayed', 'full_name' => 'Noor Khalid Ahmed Alsayed',
        'dob' => '2006-05-27', 'gender' => 'female', 'nationality' => 'Bahrain', 'passport_no' => 'BH7777777',
        'passport_expiry' => now()->addYears(3)->toDateString(), 'passport_issuing_country' => 'Bahrain',
        'passport_issue_date' => now()->subYears(2)->toDateString(), 'phone_cc' => '+973', 'phone' => '33445566',
        'whatsapp_same' => true, 'guardian_name' => 'Khalid', 'consent_data' => true], $o);
}

it('saves the new athlete fields and copies WhatsApp from mobile', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    livewire(CreatePerson::class)->fillForm(v12Athlete())->call('create')->assertHasNoFormErrors();

    $p = Person::sole();
    expect($p->code)->toBe('BWF-AT-0001')->and($p->whatsapp)->toBe('33445566')->and($p->whatsapp_cc)->toBe('+973')
        ->and($p->guardian_name)->toBe('Khalid')->and($p->consent_data_at)->not->toBeNull();
});

it('defaults phone country codes to +973', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    livewire(CreatePerson::class)->assertSchemaStateSet(['phone_cc' => '+973', 'emergency_phone_cc' => '+973']);
});

it('labels the mobile by person type', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $label = fn (string $expected) => fn (Field $f) => $f->getLabel() === $expected;

    livewire(CreatePerson::class)->assertFormFieldExists('phone', $label('Player mobile'))
        ->fillForm(['type' => 'official'])->assertFormFieldExists('phone', $label('Mobile'));
});

it('hides medical fields in admin without view_medical', function () {
    $person = Person::factory()->create(['association_id' => $this->assoc->id, 'allergies' => 'Peanuts', 'blood_group' => 'O+',
        'insurance_policy_no' => 'INS-SECRET', 'medical_clearance_expiry' => '2031-03-04']);
    $person->injuries()->create(['injury_date' => '2025-01-02', 'status' => 'Recovered', 'description' => 'Torn ligament']);
    $sta = User::factory()->sta()->create();
    $sta->givePermissionTo(StaPermission::ManagePeople->value);
    actingInAdmin($sta);
    $page = livewire(AdminEditPerson::class, ['record' => $person->getRouteKey()])->assertFormFieldDoesNotExist('allergies');
    // Nothing medical may reach the browser: not the Livewire state, not the rendered HTML.
    expect($page->get('data.allergies'))->toBeNull()->and($page->get('data.blood_group'))->toBeNull()
        ->and($page->get('data.medical_clearance_expiry'))->toBeNull()->and($page->get('data.injuries'))->toBeEmpty();
    $page->assertDontSee('Peanuts')->assertDontSee('INS-SECRET')->assertDontSee('Torn ligament')->assertDontSee('2031-03-04');

    $page->fillForm(['first_name' => 'Changed'])->call('save')->assertHasNoFormErrors();
    expect($person->refresh()->first_name)->toBe('Changed')->and($person->allergies)->toBe('Peanuts')
        ->and($person->blood_group)->toBe('O+')->and($person->medical_clearance_expiry->toDateString())->toBe('2031-03-04')
        ->and($person->injuries()->count())->toBe(1);

    $sta->givePermissionTo(StaPermission::ViewMedical->value);
    actingInAdmin($sta->fresh());
    $page = livewire(AdminEditPerson::class, ['record' => $person->getRouteKey()])->assertFormFieldVisible('allergies');
    expect($page->get('data.allergies'))->toBe('Peanuts')->and($page->get('data.injuries'))->toHaveCount(1);
});

it('saves visas for officials', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    livewire(CreatePerson::class)->fillForm(v12Athlete(['type' => 'official', 'function' => 'Head Coach', 'passport_no' => 'BH8888888',
        'visas' => [['country' => 'Japan', 'status' => 'Approved', 'visa_no' => 'J1', 'valid_until' => now()->addMonths(6)->toDateString()]]]))
        ->call('create')->assertHasNoFormErrors();

    expect(Person::sole()->visas()->sole()->country)->toBe('Japan');
});

it('can save twice on the edit page after a visa copy is renamed', function () {
    Storage::fake(config('filesystems.private'));
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $person = Person::factory()->official()->create(['association_id' => $this->assoc->id]);

    $page = livewire(EditPerson::class, ['record' => $person->getRouteKey()])
        ->fillForm(['visas' => [['country' => 'Japan', 'status' => 'Applied',
            'copy_path' => [UploadedFile::fake()->create('v.pdf', 5, 'application/pdf')]]]])
        ->call('save')->assertHasNoFormErrors();
    $visa = $person->visas()->sole();
    $stored = "visas/{$person->code}_visa_{$visa->id}.pdf";
    expect($visa->copy_path)->toBe($stored);

    $page->call('save')->assertHasNoFormErrors();
    expect($visa->refresh()->copy_path)->toBe($stored)->and($person->visas()->count())->toBe(1);
    Storage::disk(config('filesystems.private'))->assertExists($stored);
});

it('stores a visa copy uploaded on create', function () {
    Storage::fake(config('filesystems.private'));
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    livewire(CreatePerson::class)->fillForm(v12Athlete(['type' => 'official', 'function' => 'Head Coach',
        'visas' => [['country' => 'Japan', 'status' => 'Applied', 'copy_path' => [UploadedFile::fake()->create('v.pdf', 5, 'application/pdf')]]]]))
        ->call('create')->assertHasNoFormErrors();

    $visa = Person::sole()->visas()->sole();
    expect($visa->copy_path)->toBe("visas/BWF-TO-0001_visa_{$visa->id}.pdf");
    Storage::disk(config('filesystems.private'))->assertExists($visa->copy_path);
});

it('refuses a tampered visa copy path', function () {
    Storage::fake(config('filesystems.private'));
    Storage::disk(config('filesystems.private'))->put('scans/victim.pdf', 'secret');
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $person = Person::factory()->official()->create(['association_id' => $this->assoc->id]);

    livewire(EditPerson::class, ['record' => $person->getRouteKey()])
        ->fillForm(['visas' => [['country' => 'Japan', 'status' => 'Applied', 'copy_path' => ['x' => 'scans/victim.pdf']]]])
        ->call('save')->assertHasFormErrors(['visas.0.copy_path']);

    expect($person->visas()->count())->toBe(0);
});

it('deletes the visa copy file when the visa model or its row is removed', function () {
    Storage::fake(config('filesystems.private'));
    $disk = Storage::disk(config('filesystems.private'));
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
    $person = Person::factory()->official()->create(['association_id' => $this->assoc->id]);
    $a = $person->visas()->create(['country' => 'Japan', 'status' => 'Applied', 'copy_path' => 'visas/a.pdf']);
    $b = $person->visas()->create(['country' => 'China', 'status' => 'Applied', 'copy_path' => 'visas/b.pdf']);
    $disk->put('visas/a.pdf', 'a');
    $disk->put('visas/b.pdf', 'b');

    $a->delete();
    $disk->assertMissing('visas/a.pdf');

    livewire(EditPerson::class, ['record' => $person->getRouteKey()])->fillForm(['visas' => []])->call('save')->assertHasNoFormErrors();
    $disk->assertMissing('visas/b.pdf');
    expect($person->visas()->count())->toBe(0);

    $c = $person->visas()->create(['country' => 'Peru', 'status' => 'Applied', 'copy_path' => 'visas/c.pdf']);
    $disk->put('visas/c.pdf', 'c');
    $person->delete();
    $disk->assertMissing('visas/c.pdf');
});
