<?php

use App\Models\Person;

it('normalises Milestone 1 nationality and phone free text', function () {
    $ids = collect([
        ['nationality' => 'bahrain', 'phone' => '+973 3300-0000', 'emergency_phone' => '0097333111222', 'guardian_phone' => '(33) 000.111', 'whatsapp' => '+1 555 0100'],
        ['nationality' => 'BHR', 'phone' => '+97333000000'],
        ['nationality' => 'Bahraini', 'phone' => 'call me', 'whatsapp' => '+999 123456'],
    ])->map(function ($legacy) {
        $p = Person::factory()->create();
        DB::table('people')->where('id', $p->id)->update($legacy);

        return $p->id;
    });

    (require database_path('migrations/2026_11_09_000700_normalise_people_nationality_and_phones.php'))->up();

    $a = DB::table('people')->find($ids[0]);
    expect($a->nationality)->toBe('Bahrain')
        ->and([$a->phone_cc, $a->phone])->toBe(['+973', '33000000'])
        ->and([$a->emergency_phone_cc, $a->emergency_phone])->toBe(['+973', '33111222'])
        ->and([$a->guardian_phone_cc, $a->guardian_phone])->toBe(['+973', '33 000111'])
        ->and([$a->whatsapp_cc, $a->whatsapp])->toBe(['+1', '555 0100']);

    $b = DB::table('people')->find($ids[1]);
    expect($b->nationality)->toBe('Bahrain')->and([$b->phone_cc, $b->phone])->toBe(['+973', '33000000']);

    $c = DB::table('people')->find($ids[2]);
    expect($c->nationality)->toBe('Bahraini')->and($c->phone)->toBe('call me')->and($c->whatsapp)->toBe('+999 123456');
});
