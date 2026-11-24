<?php

use App\Filament\Portal\Resources\People\Pages\EditPerson;
use App\Models\Association;
use App\Models\Person;
use App\Models\User;
use App\Services\Mrz\MrzReader;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->disk = Storage::fake(config('filesystems.private'));
    $this->assoc = Association::factory()->create();
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
});

it('keeps the main scan first, promotes the first other page when the main one goes, and deletes removed pages', function () {
    foreach (['scans/main.pdf', 'scans/p2.pdf', 'scans/p3.pdf'] as $f) {
        $this->disk->put($f, 'x');
    }
    $p = Person::factory()->create(['association_id' => $this->assoc->id, 'passport_scan_path' => 'scans/main.pdf', 'passport_scan_extra' => ['scans/p2.pdf', 'scans/p3.pdf']]);
    $main = $p->refresh()->passport_scan_path;
    expect($p->passportScans())->toBe([$main, 'scans/p2.pdf', 'scans/p3.pdf']);

    $p->update(['passport_scan_extra' => ['scans/p3.pdf']]);
    $this->disk->assertMissing('scans/p2.pdf');

    $p->update(['passport_scan_path' => null]);
    expect($p->refresh()->passport_scan_path)->toBe("scans/{$p->code}_passport.pdf")->and($p->passport_scan_extra)->toBeNull()
        ->and($p->missingFields())->not->toContain('Passport scan');
    $this->disk->assertMissing('scans/p3.pdf')->assertExists($p->passport_scan_path);

    $p->update(['passport_scan_extra' => ['scans/p4.pdf']]);
    $this->disk->put('scans/p4.pdf', 'x');
    $p->delete();
    $this->disk->assertMissing(['scans/p4.pdf', $p->passport_scan_path]);
});

it('adds other passport pages on the person form', function () {
    $this->disk->put('scans/main.pdf', 'x');
    $p = Person::factory()->create(['association_id' => $this->assoc->id, 'passport_scan_path' => 'scans/main.pdf']);

    livewire(EditPerson::class, ['record' => $p->getRouteKey()])
        ->assertSee('Add another file')
        ->fillForm(['passport_scan_extra' => [UploadedFile::fake()->create('p2.pdf', 5, 'application/pdf'), UploadedFile::fake()->image('p3.jpg')]])
        ->set('data.cross_checked', true)->call('save')
        ->assertHasNoFormErrors();

    expect($p->refresh()->passport_scan_extra)->toHaveCount(2)->and($p->passportScans())->toHaveCount(3);
    $this->disk->assertExists($p->passport_scan_extra);
});

it('reads the first passport file that has a readable MRZ, never another association\'s file', function () {
    $this->disk->put('scans/main.jpg', 'blurry');
    $this->disk->put('scans/p2.jpg', 'mrz');
    $this->disk->put('scans/OTHER.jpg', 'mrz');
    $p = Person::factory()->create(['association_id' => $this->assoc->id, 'passport_scan_path' => 'scans/main.jpg', 'passport_scan_extra' => ['scans/p2.jpg']]);
    $seen = new ArrayObject;
    app()->instance(MrzReader::class, new class($seen) implements MrzReader
    {
        public function __construct(private ArrayObject $seen) {}

        public function read(string $absolutePath): ?array
        {
            $this->seen[] = file_get_contents($absolutePath);

            return file_get_contents($absolutePath) === 'mrz'
                ? ['P<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<', 'BH12345676BHR0605276F3101159<<<<<<<<<<<<<<00'] : null;
        }
    });

    $page = livewire(EditPerson::class, ['record' => $p->getRouteKey()])
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertSchemaStateSet(['passport_no' => 'BH1234567']);
    expect($seen->getArrayCopy())->toBe(['blurry', 'mrz']);

    $seen->exchangeArray([]);
    $page->set('data.passport_no', null)->set('data.passport_scan_extra', ['k' => 'scans/OTHER.jpg'])
        ->callAction(TestAction::make('readPassport')->schemaComponent('passport_scan_path'))
        ->assertSchemaStateSet(['passport_no' => null]);
    expect($seen->getArrayCopy())->toBe(['blurry']);
});
