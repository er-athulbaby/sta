<?php

use App\Enums\PersonType;
use App\Enums\Role;
use App\Models\Association;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\ClothingItemSeeder;
use Database\Seeders\TriathlonTestSeeder;

it('creates the triathlon association with complete people and two logins, and can run twice', function () {
    putenv('TEST_DATA_PASSWORD=test-data-pass-1');
    $this->seed(ClothingItemSeeder::class);
    $this->seed(TriathlonTestSeeder::class);
    $this->seed(TriathlonTestSeeder::class);
    putenv('TEST_DATA_PASSWORD');

    $a = Association::where('code', 'BTRI')->sole();
    $people = Person::withoutGlobalScopes()->where('association_id', $a->id)->get();

    expect($people->where('type', PersonType::Athlete))->toHaveCount(15)
        ->and($people->where('type', PersonType::Official))->toHaveCount(5)
        ->and($people->every(fn (Person $p) => $p->isComplete()))->toBeTrue()
        ->and(User::where('association_id', $a->id)->pluck('role')->all())->toEqualCanonicalizing([Role::AssocAdmin, Role::AssocEditor]);
});

it('refuses without a password', function () {
    putenv('TEST_DATA_PASSWORD');
    $this->seed(TriathlonTestSeeder::class);
})->throws(RuntimeException::class);
