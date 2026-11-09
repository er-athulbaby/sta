<?php

use App\Exports\PeopleTemplateExport;
use App\Filament\Portal\Pages\ImportPeople;
use App\Models\Association;
use App\Models\Person;
use App\Models\User;
use App\Services\PeopleImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

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
        'type' => 'athlete', 'full_name' => 'Sara Ali', 'first_name' => 'Sara', 'second_name' => '', 'third_name' => '', 'last_name' => 'Ali',
        'date_of_birth' => '2007-04-02', 'gender' => 'female', 'nationality' => 'Bahrain', 'height_cm' => 165, 'weight_kg' => 55,
        'passport_no' => 'BH1111111', 'passport_issuing_country' => 'Bahrain', 'passport_issue_date' => '2023-01-01', 'passport_expiry' => '2029-01-01', 'cpr_no' => '070402111', 'cpr_expiry' => '2028-01-01',
        'function' => '', 'mobile' => '', 'email' => '',
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
    $this->actingAs(User::factory()->assocEditor($assoc)->create())
        ->get("/portal/{$assoc->id}/import-people")->assertOk()->assertSee('Download template');
});

it('keys errors by the real sheet row when blank rows are present', function () {
    $result = app(PeopleImporter::class)->import(xlsxWith([
        row(), array_fill_keys(PeopleTemplateExport::COLUMNS, ''), row(['passport_no' => 'BH2222222', 'cpr_no' => '070402222']),
        row(['passport_no' => 'BH3333333', 'cpr_no' => '070402333', 'nationality' => '']),
    ]), Association::factory()->create());

    expect(array_keys($result->errors))->toBe([5])->and(Person::count())->toBe(0);
});

it('does not skip rows that lack first name and passport', function () {
    $result = app(PeopleImporter::class)->import(xlsxWith([
        ['last_name' => 'Ali', 'date_of_birth' => '2007-04-02'],
    ]), Association::factory()->create());

    expect(array_keys($result->errors))->toBe([2]);
});

it('returns a friendly error for a file that is not a spreadsheet', function () {
    $path = storage_path('app/testing/bad-'.uniqid().'.xlsx');
    file_put_contents($path, 'not a spreadsheet');
    $result = app(PeopleImporter::class)->import($path, Association::factory()->create());

    expect($result->ok())->toBeFalse()->and(array_keys($result->errors))->toBe([1])->and(Person::count())->toBe(0);
});

it('imports the untouched default template', function () {
    $path = storage_path('app/testing/tpl-'.uniqid().'.xlsx');
    (new PeopleTemplateExport)->writeTo($path);
    $result = app(PeopleImporter::class)->import($path, Association::factory()->create());

    expect($result->errors)->toBe([])->and($result->created)->toBe(1);
});

it('ignores function for athletes', function () {
    $result = app(PeopleImporter::class)->import(xlsxWith([row(['function' => 'whatever'])]), Association::factory()->create());

    expect($result->ok())->toBeTrue()->and(Person::sole()->function)->toBeNull();
});

it('stores a leading-zero CPR exactly and scopes to the given association', function () {
    $assoc = Association::factory()->create();
    app(PeopleImporter::class)->import(xlsxWith([row(['cpr_no' => '012345678'])]), $assoc);

    $p = Person::sole();
    expect($p->cpr_no)->toBe('012345678')->and($p->association_id)->toBe($assoc->id);
});

it('rejects files over the row cap', function () {
    $rows = [];
    for ($i = 0; $i < 501; $i++) {
        $rows[] = row(['passport_no' => 'P'.$i, 'cpr_no' => '1'.$i]);
    }
    $result = app(PeopleImporter::class)->import(xlsxWith($rows), Association::factory()->create());

    expect($result->ok())->toBeFalse()->and(array_keys($result->errors))->toBe([1])->and(Person::count())->toBe(0);
});

it('imports an uploaded file through the page and removes the upload afterwards', function () {
    Storage::fake('local');
    $assoc = Association::factory()->create();
    actingInPortal(User::factory()->assocEditor($assoc)->create());

    livewire(ImportPeople::class)
        ->set('data.file', UploadedFile::fake()->createWithContent('people.xlsx', file_get_contents(xlsxWith([row()]))))
        ->call('import')
        ->assertHasNoFormErrors();

    expect(Person::where('association_id', $assoc->id)->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses a tampered file path and never deletes or imports that file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('scans/victim.pdf', 'secret');
    $assoc = Association::factory()->create();
    actingInPortal(User::factory()->assocEditor($assoc)->create());

    livewire(ImportPeople::class)
        ->set('data.file', ['x' => 'scans/victim.pdf'])
        ->call('import')
        ->assertHasFormErrors(['file']);

    Storage::disk('local')->assertExists('scans/victim.pdf');
    expect(Person::count())->toBe(0);
});

it('imports the v1.2 columns and assigns codes', function () {
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $result = app(PeopleImporter::class)->import(xlsxWith([row(['full_name' => 'Sara Ali', 'passport_issuing_country' => 'Bahrain',
        'passport_issue_date' => '2024-01-10', 'mobile' => '33445566', 'email' => 'sara@x.bh'])]), $assoc);

    $p = Person::sole();
    expect($result->ok())->toBeTrue()->and($p->code)->toBe('BWF-AT-0001')->and($p->full_name)->toBe('Sara Ali')
        ->and($p->passport_issuing_country)->toBe('Bahrain')->and($p->phone)->toBe('33445566')->and($p->phone_cc)->toBe('+973');
});

it('rejects an unknown issuing country', function () {
    $result = app(PeopleImporter::class)->import(xlsxWith([row(['passport_issuing_country' => 'Atlantis'])]), Association::factory()->create());
    expect($result->ok())->toBeFalse();
});

it('rejects a nationality that is not in the country list', function () {
    $result = app(PeopleImporter::class)->import(xlsxWith([row(['nationality' => 'Bahraini'])]), Association::factory()->create());
    expect($result->ok())->toBeFalse()->and(Person::count())->toBe(0);
});

it('rejects an official function outside the form list', function () {
    $result = app(PeopleImporter::class)->import(xlsxWith([row(['type' => 'official', 'function' => 'Coach'])]), Association::factory()->create());
    expect($result->ok())->toBeFalse();
});
