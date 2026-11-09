<?php

use App\Models\Person;

it('lists what an athlete still needs', function () {
    $p = Person::factory()->create(['full_name' => null, 'photo_path' => null, 'passport_issuing_country' => null,
        'passport_issue_date' => null, 'passport_scan_path' => null, 'cpr_no' => null]);

    expect($p->missingFields())->toBe(['Full name', 'Photo', 'Passport issuing country', 'Passport issue date', 'Passport scan', 'CPR number'])
        ->and($p->isComplete())->toBeFalse();
});

it('lists what an official still needs and passes when complete', function () {
    $o = Person::factory()->official()->create(['whatsapp' => null, 'whatsapp_same' => false, 'emergency_name' => null]);
    expect($o->missingFields())->toContain('WhatsApp')->toContain('Emergency contact name');

    $full = Person::factory()->official()->complete()->create();
    expect($full->missingFields())->toBe([])->and($full->isComplete())->toBeTrue();
});

it('defaults country codes so whatsapp_same works without a phone_cc', function () {
    $o = Person::factory()->official()->create(['phone' => '33000000', 'whatsapp_same' => true]);

    expect($o->whatsapp_cc)->toBe('+973')->and($o->whatsapp)->toBe('33000000');
});
