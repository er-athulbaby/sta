<?php

use App\Models\Association;
use App\Models\Person;

it('numbers athletes and officials per association and type', function () {
    $bwf = Association::factory()->create(['code' => 'BWF']);
    $bjf = Association::factory()->create(['code' => 'BJF']);

    $a1 = Person::factory()->create(['association_id' => $bwf->id]);
    $a2 = Person::factory()->create(['association_id' => $bwf->id]);
    $o1 = Person::factory()->official()->create(['association_id' => $bwf->id]);
    $j1 = Person::factory()->create(['association_id' => $bjf->id]);

    expect([$a1->code, $a2->code, $o1->code, $j1->code])->toBe(['BWF-AT-0001', 'BWF-AT-0002', 'BWF-TO-0001', 'BJF-AT-0001']);
});

it('never reuses a number after delete and keeps the code on update', function () {
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $a1 = Person::factory()->create(['association_id' => $assoc->id]);
    Person::factory()->create(['association_id' => $assoc->id])->delete();
    $a3 = Person::factory()->create(['association_id' => $assoc->id]);
    $a1->update(['first_name' => 'Changed']);

    expect($a3->code)->toBe('BWF-AT-0003')->and($a1->fresh()->code)->toBe('BWF-AT-0001');
});

it('codes officials correctly when type is a raw string', function () {
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $o = Person::factory()->create(['association_id' => $assoc->id, 'type' => 'official']);

    expect($o->code)->toBe('BWF-TO-0001');
});

it('provides countries and phone codes', function () {
    expect(config('countries.BHR'))->toBe('Bahrain')
        ->and(count(config('countries')))->toBeGreaterThanOrEqual(249)
        ->and(array_key_first(config('phone_codes')))->toBe('+973');
});

it('removes visa copy files when a person is deleted', function () {
    $disk = config('filesystems.private');
    Storage::fake($disk);
    Storage::disk($disk)->put('visas/a.pdf', 'x');
    $person = Person::factory()->create();
    $person->visas()->create(['country' => 'Japan', 'status' => 'Approved', 'copy_path' => 'visas/a.pdf']);

    $person->delete();

    Storage::disk($disk)->assertMissing('visas/a.pdf');
    expect(PersonVisa::count())->toBe(0);
});
