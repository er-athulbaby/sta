<?php

use App\Enums\AppliesTo;
use App\Enums\PersonType;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('encrypts passport and CPR numbers at rest', function () {
    $p = Person::factory()->create(['passport_no' => 'BH1234567', 'cpr_no' => '990101234']);
    $raw = DB::table('people')->where('id', $p->id)->first();

    expect($raw->passport_no)->not->toContain('BH1234567')
        ->and($raw->cpr_no)->not->toContain('990101234')
        ->and($p->fresh()->passport_no)->toBe('BH1234567');
});

it('enforces system-wide uniqueness ignoring case and spaces', function () {
    Person::factory()->create(['passport_no' => 'bh 123 4567']);
    expect(fn () => Person::factory()->create(['passport_no' => 'BH1234567']))->toThrow(QueryException::class)
        ->and(Person::documentExists('passport_no_hash', 'BH 1234567'))->toBeTrue()
        ->and(Person::documentExists('passport_no_hash', 'X999'))->toBeFalse();
});

it('scopes people to the signed-in association user', function () {
    $mine = Person::factory()->create();
    Person::factory()->create();
    $this->actingAs(User::factory()->assocEditor($mine->association)->create());

    expect(Person::pluck('id')->all())->toBe([$mine->id]);
});

it('does not scope STA users', function () {
    Person::factory()->count(2)->create();
    $this->actingAs(User::factory()->sta()->create());
    expect(Person::count())->toBe(2);
});

it('fills association_id from the signed-in association user', function () {
    $assoc = Association::factory()->create();
    $this->actingAs(User::factory()->assocEditor($assoc)->create());
    $p = Person::factory()->make(['association_id' => null]);
    $p->save();
    expect($p->association_id)->toBe($assoc->id);
});

it('syncs sizes and validates them against the item', function () {
    $shirt = ClothingItem::factory()->create(['sizes' => ['S', 'M', 'L'], 'applies_to' => AppliesTo::Both]);
    $suit = ClothingItem::factory()->create(['sizes' => ['48', '50'], 'applies_to' => AppliesTo::Official]);
    $athlete = Person::factory()->create(['type' => PersonType::Athlete]);

    $athlete->syncSizes([$shirt->id => 'M']);
    expect($athlete->sizesMap())->toBe([$shirt->id => 'M']);

    expect(fn () => $athlete->syncSizes([$shirt->id => 'XXL']))->toThrow(ValidationException::class)
        ->and(fn () => $athlete->syncSizes([$suit->id => '48']))->toThrow(ValidationException::class);

    $athlete->syncSizes([$shirt->id => null]);
    expect($athlete->sizesMap())->toBe([]);
});

it('clears official-only fields on athletes and computes age', function () {
    $p = Person::factory()->create(['type' => PersonType::Athlete, 'function' => 'Coach', 'dob' => '2008-06-15']);
    expect($p->function)->toBeNull()
        ->and($p->age(\Carbon\Carbon::parse('2026-06-14')))->toBe(17)
        ->and($p->age(\Carbon\Carbon::parse('2026-06-15')))->toBe(18);
});

it('deletes the stored files when a person is deleted', function () {
    $disk = config('filesystems.private');
    Storage::fake($disk);
    $files = ['people/photo.jpg', 'people/passport.pdf', 'people/cpr.pdf'];
    foreach ($files as $f) {
        Storage::disk($disk)->put($f, 'x');
    }
    $p = Person::factory()->create([
        'photo_path' => $files[0], 'passport_scan_path' => $files[1], 'cpr_scan_path' => $files[2],
    ]);

    $p->delete();

    Storage::disk($disk)->assertMissing($files);
});

it('ignores a spoofed association_id from an association user', function () {
    $a = Association::factory()->create();
    $b = Association::factory()->create();
    $this->actingAs(User::factory()->assocEditor($a)->create());

    $p = Person::factory()->create(['association_id' => $b->id]);

    expect($p->association_id)->toBe($a->id);
});

it('allows several people without a CPR number', function () {
    $one = Person::factory()->create(['cpr_no' => null]);
    $two = Person::factory()->create(['cpr_no' => null]);

    expect($one->cpr_no_hash)->toBeNull()
        ->and($two->cpr_no_hash)->toBeNull()
        ->and(Person::documentExists('cpr_no_hash', 'X1'))->toBeFalse();
});

it('keeps official-only fields on officials', function () {
    $p = Person::factory()->official()->create(['function' => 'Coach', 'phone' => '+97333111111', 'email' => 'o@example.com'])->fresh();

    expect($p->function)->toBe('Coach')
        ->and($p->phone)->toBe('+97333111111')
        ->and($p->email)->toBe('o@example.com');
});

it('rolls back earlier size changes when a later one is invalid', function () {
    $shirt = ClothingItem::factory()->create(['sizes' => ['M', 'L'], 'applies_to' => AppliesTo::Both]);
    $suit = ClothingItem::factory()->create(['sizes' => ['48'], 'applies_to' => AppliesTo::Official]);
    $athlete = Person::factory()->create(['type' => PersonType::Athlete]);
    $athlete->syncSizes([$shirt->id => 'M']);

    expect(fn () => $athlete->syncSizes([$shirt->id => 'L', $suit->id => '48']))->toThrow(ValidationException::class)
        ->and($athlete->sizesMap())->toBe([$shirt->id => 'M']);
});
