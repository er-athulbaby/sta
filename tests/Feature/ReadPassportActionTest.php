<?php

use App\Filament\Portal\Resources\People\Pages\CreatePerson;
use App\Filament\Portal\Resources\People\Pages\EditPerson;
use App\Models\Association;
use App\Models\Person;
use App\Models\User;
use App\Services\Mrz\MrzReader;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

function fakeReader(?array $lines): void
{
    app()->instance(MrzReader::class, new class($lines) implements MrzReader
    {
        public function __construct(private ?array $lines) {}

        public function read(string $absolutePath): ?array
        {
            return $this->lines;
        }
    });
}

beforeEach(function () {
    Storage::fake(config('filesystems.private'));
    actingInPortal(User::factory()->assocEditor(Association::factory()->create(['code' => 'BWF']))->create());
});

it('fills the form from the passport MRZ', function () {
    fakeReader(['P<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<', 'BH12345676BHR0605276F3101159<<<<<<<<<<<<<<00']);

    livewire(CreatePerson::class)
        ->fillForm(['passport_scan_path' => UploadedFile::fake()->image('passport.jpg')])
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertSchemaStateSet([
            'full_name' => 'Noor Khalid Ahmed Alsayed', 'first_name' => 'Noor', 'second_name' => 'Khalid', 'third_name' => 'Ahmed',
            'last_name' => 'Alsayed', 'passport_no' => 'BH1234567', 'passport_issuing_country' => 'Bahrain', 'nationality' => 'Bahrain',
            'dob' => '2006-05-27', 'gender' => 'female', 'passport_expiry' => '2031-01-15',
        ])
        ->assertNotified('Passport read — please check the details before saving');
});

it('leaves fields whose check digit fails empty and warns', function () {
    fakeReader(['P<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<', 'BH12345676BHR0605275F3101159<<<<<<<<<<<<<<00']);

    livewire(CreatePerson::class)
        ->fillForm(['passport_scan_path' => UploadedFile::fake()->image('passport.jpg'), 'dob' => null])
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertSchemaStateSet(['dob' => null, 'passport_no' => 'BH1234567']);
});

it('says when the passport cannot be read', function () {
    fakeReader(null);

    livewire(CreatePerson::class)
        ->fillForm(['passport_scan_path' => UploadedFile::fake()->image('passport.jpg'), 'first_name' => 'Keep'])
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertSchemaStateSet(['first_name' => 'Keep'])
        ->assertNotified("Couldn't read the passport. Try a clearer, flat scan.");
});

it('never reads a stored path that does not belong to the record', function () {
    Storage::disk(config('filesystems.private'))->put('scans/OTHER_ASSOC.jpg', 'secret');
    app()->instance(MrzReader::class, new class implements MrzReader
    {
        public function read(string $absolutePath): ?array
        {
            throw new RuntimeException('reader must not be called');
        }
    });

    livewire(CreatePerson::class)
        ->set('data.passport_scan_path', ['k' => 'scans/OTHER_ASSOC.jpg'])
        ->set('data.first_name', 'Keep')
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertSchemaStateSet(['first_name' => 'Keep'])
        ->assertNotified("Couldn't read the passport. Try a clearer, flat scan.");
});

it('reads the record\'s own stored passport scan on edit and removes the temp copy', function () {
    Storage::disk(config('filesystems.private'))->put('scans/own.jpg', 'img');
    $person = Person::factory()->create(['association_id' => Association::first()->id, 'passport_scan_path' => 'scans/own.jpg']);
    $seen = new ArrayObject;
    app()->instance(MrzReader::class, new class($seen) implements MrzReader
    {
        public function __construct(private ArrayObject $seen) {}

        public function read(string $absolutePath): ?array
        {
            $this->seen[] = $absolutePath;
            expect(file_get_contents($absolutePath))->toBe('img');

            return ['P<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<', 'BH12345676BHR0605276F3101159<<<<<<<<<<<<<<00'];
        }
    });

    livewire(EditPerson::class, ['record' => $person->getRouteKey()])
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertSchemaStateSet(['passport_no' => 'BH1234567', 'last_name' => 'Alsayed']);

    expect($seen)->toHaveCount(1)->and(file_exists($seen[0]))->toBeFalse();
});

it('treats a throwing reader as unreadable and reports it', function () {
    app()->instance(MrzReader::class, new class implements MrzReader
    {
        public function read(string $absolutePath): ?array
        {
            throw new RuntimeException('tesseract missing');
        }
    });
    Exceptions::fake();

    livewire(CreatePerson::class)
        ->fillForm(['passport_scan_path' => UploadedFile::fake()->image('passport.jpg')])
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertNotified("Couldn't read the passport. Try a clearer, flat scan.");
    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'tesseract missing');
});
