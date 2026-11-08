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
