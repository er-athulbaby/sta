<?php

use App\Exports\PeopleTemplateExport;
use App\Models\Association;
use App\Models\Person;
use App\Services\PeopleImporter;

function xlsxWith(array $rows): string
{
    $dir = storage_path('app/testing');
    is_dir($dir) || mkdir($dir, 0777, true);
    $path = $dir.'/import-test-'.uniqid().'.xlsx';
    (new PeopleTemplateExport($rows))->writeTo($path);

    return $path;
}

function row(array $overrides = []): array
{
    return array_merge([
        'type' => 'athlete', 'first_name' => 'Sara', 'second_name' => '', 'third_name' => '', 'last_name' => 'Ali',
        'date_of_birth' => '2007-04-02', 'gender' => 'female', 'nationality' => 'Bahrain', 'height_cm' => 165, 'weight_kg' => 55,
        'passport_no' => 'BH1111111', 'passport_expiry' => '2029-01-01', 'cpr_no' => '070402111', 'cpr_expiry' => '2028-01-01',
        'function' => '', 'phone' => '', 'email' => '',
    ], $overrides);
}

it('imports all rows when every row is valid', function () {
    $assoc = Association::factory()->create();
    $result = app(PeopleImporter::class)->import(xlsxWith([row(), row(['first_name' => 'Huda', 'passport_no' => 'BH2222222', 'cpr_no' => '070402222'])]), $assoc);

    expect($result->ok())->toBeTrue()->and($result->created)->toBe(2)
        ->and(Person::where('association_id', $assoc->id)->count())->toBe(2);
});

it('reports errors with spreadsheet row numbers and imports nothing', function () {
    Person::factory()->create(['passport_no' => 'BH9999999']);
    $result = app(PeopleImporter::class)->import(xlsxWith([
        row(),
        row(['passport_no' => 'BH9999999', 'cpr_no' => '070402333']),           // row 3: duplicate in system
        row(['type' => 'official', 'passport_no' => 'BH4444444', 'cpr_no' => '']), // row 4: official without function
        row(['passport_no' => 'BH1111111', 'cpr_no' => '070402555']),           // row 5: duplicate inside the file
    ]), Association::factory()->create());

    expect($result->ok())->toBeFalse()->and($result->created)->toBe(0)
        ->and(array_keys($result->errors))->toBe([3, 4, 5])
        ->and(Person::count())->toBe(1);
});

it('accepts Excel serial dates', function () {
    $result = app(PeopleImporter::class)->import(xlsxWith([row(['date_of_birth' => 39174, 'passport_expiry' => 47119, 'cpr_no' => '', 'cpr_expiry' => ''])]), Association::factory()->create());
    expect($result->ok())->toBeTrue()->and(Person::sole()->dob->toDateString())->toBe('2007-04-02');
});

it('opens the import page for association users', function () {
    $assoc = Association::factory()->create();
    $this->actingAs(\App\Models\User::factory()->assocEditor($assoc)->create())
        ->get("/portal/{$assoc->id}/import-people")->assertOk()->assertSee('Download template');
});
