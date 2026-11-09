<?php

use App\Filament\Portal\Resources\People\Pages\ListPeople;
use App\Models\ClothingItem;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\ClothingItemSeeder;

use function Pest\Livewire\livewire;

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

it('shows code and completeness in the people list', function () {
    $incomplete = Person::factory()->create(['full_name' => null]);
    actingInPortal(User::factory()->assocEditor($incomplete->association)->create());

    livewire(ListPeople::class)
        ->assertTableColumnStateSet('code', $incomplete->code, $incomplete)
        ->assertTableColumnStateSet('completeness', 'Incomplete', $incomplete);
});

it('seeds T-Shirt, Suit and Shoes once', function () {
    $this->seed(ClothingItemSeeder::class);
    $this->seed(ClothingItemSeeder::class);
    expect(ClothingItem::pluck('name')->sort()->values()->all())->toBe(['Shoes', 'Suit', 'T-Shirt']);
});
