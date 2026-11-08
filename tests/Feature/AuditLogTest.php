<?php

use App\Enums\StaPermission;
use App\Models\Person;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('logs person changes with the causer and never the document numbers', function () {
    $person = Person::factory()->create(['passport_no' => 'BH5555555', 'cpr_no' => '010101555']);
    $editor = User::factory()->assocEditor($person->association)->create();
    $this->actingAs($editor);

    $person->update(['first_name' => 'Changed', 'passport_no' => 'BH6666666']);

    $log = Activity::where('subject_type', Person::class)->where('event', 'updated')->latest('id')->first();
    expect($log->causer_id)->toBe($editor->id)
        ->and($log->properties['attributes']['first_name'])->toBe('Changed')
        ->and($log->properties['documents_changed'])->toBe(['passport_no'])
        ->and(json_encode($log->properties))->not->toContain('BH6666666')->not->toContain('BH5555555');
});

it('shows the audit log only with the permission', function () {
    $sta = User::factory()->sta()->create();
    $this->actingAs($sta)->get('/admin/activity-logs')->assertForbidden();

    $sta->givePermissionTo(StaPermission::ViewAuditLog->value);
    $this->actingAs($sta)->get('/admin/activity-logs')->assertOk();
});

use function Pest\Livewire\livewire;

function docUpdate(array $change): array
{
    $person = Person::factory()->create(['passport_no' => 'BH5555555', 'cpr_no' => '010101555']);
    test()->actingAs(User::factory()->assocEditor($person->association)->create());
    $before = Activity::where('event', 'updated')->count();
    $hashes = [$person->passport_no_hash, $person->cpr_no_hash];
    $person->update($change);
    expect(Activity::where('event', 'updated')->count())->toBe($before + 1);
    $log = Activity::where('event', 'updated')->latest('id')->first();

    return [$log, json_encode($log->properties), $hashes];
}

it('logs a passport-only change without values or hashes', function () {
    [$log, $json, $hashes] = docUpdate(['passport_no' => 'BH6666666']);
    expect($log->properties['documents_changed'])->toBe(['passport_no'])
        ->and($json)->not->toContain('BH5555555')->not->toContain('BH6666666')
        ->not->toContain('passport_no_hash')->not->toContain('cpr_no_hash')
        ->not->toContain($hashes[0])->not->toContain($hashes[1]);
});

it('logs a cpr-only change without values or hashes', function () {
    [$log, $json, $hashes] = docUpdate(['cpr_no' => '020202666']);
    expect($log->properties['documents_changed'])->toBe(['cpr_no'])
        ->and($json)->not->toContain('010101555')->not->toContain('020202666')
        ->not->toContain('passport_no_hash')->not->toContain('cpr_no_hash')
        ->not->toContain($hashes[0])->not->toContain($hashes[1]);
});

it('logs person deletion without document numbers', function () {
    $person = Person::factory()->create(['passport_no' => 'BH5555555', 'cpr_no' => '010101555']);
    $person->delete();
    $log = Activity::where('event', 'deleted')->where('subject_type', Person::class)->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and(json_encode($log->properties))->not->toContain('BH5555555')->not->toContain('010101555');
});

it('logs user name changes but never the password', function () {
    $user = User::factory()->create();
    $user->update(['name' => 'Renamed', 'password' => 'new-secret-pw']);
    $log = Activity::where('subject_type', User::class)->where('event', 'updated')->latest('id')->first();
    expect($log->properties['attributes']['name'])->toBe('Renamed')
        ->and($log->properties['attributes'])->not->toHaveKey('password')
        ->and(json_encode($log->properties))->not->toContain('password');
});

it('lets a super admin see audit records', function () {
    $admin = User::factory()->superAdmin()->create();
    Person::factory()->create();
    $this->actingAs($admin)->get('/admin/activity-logs')->assertOk();
    livewire(\App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs::class)
        ->assertCountTableRecords(Activity::count());
    expect(Activity::count())->toBeGreaterThan(0);
});
