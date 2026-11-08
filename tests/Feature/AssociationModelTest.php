<?php

use App\Models\Association;
use Illuminate\Database\QueryException;

it('requires a unique name and email', function () {
    Association::factory()->create(['name' => 'Bahrain Athletics', 'email' => 'a@x.bh']);
    expect(fn () => Association::factory()->create(['name' => 'Bahrain Athletics']))->toThrow(QueryException::class)
        ->and(fn () => Association::factory()->create(['email' => 'a@x.bh']))->toThrow(QueryException::class);
});

it('keeps profile fields optional', function () {
    $a = Association::create(['name' => 'Squash', 'email' => 's@x.bh']);
    expect($a->logo_path)->toBeNull()->and($a->phone)->toBeNull();
});
