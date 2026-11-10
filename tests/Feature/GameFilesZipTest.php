<?php

use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\Pages\GameExportFiles;
use App\Jobs\BuildGameFilesZip;
use App\Models\Association;
use App\Models\ExportLayout;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\GameFiles\GameFileExporter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->disk = Storage::fake(config('filesystems.private'));
    $this->disk->buildTemporaryUrlsUsing(fn (string $path) => 'https://files.test/'.$path);

    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game->associations()->attach($this->assoc->id);
    $gs = GameSport::factory()->create(['game_id' => $this->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id])->id]);
    $ge = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id]);
    // Same last name on purpose: the pattern below makes their photo names collide.
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan',
        'photo_path' => 'photos/a.jpg', 'passport_scan_path' => 'scans/a.pdf', 'wada_certificate_path' => 'scans/w.pdf']);
    $omar = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Hasan',
        'photo_path' => 'photos/o.JPG']);
    $this->disk->put('photos/a.jpg', 'ali-photo');
    $this->disk->put('scans/a.pdf', 'ali-passport');
    $this->disk->put('scans/w.pdf', 'ali-wada');
    $this->disk->put('photos/o.JPG', 'omar-photo');
    $s = Submission::for($this->game, $this->assoc, 3);
    foreach ([$this->ali, $omar] as $p) {
        $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $ge->id, 'game_sport_id' => $gs->id]);
    }
    $s->update(['status' => SubmissionStatus::Submitted]);

    $this->layout = ExportLayout::create(['game_id' => $this->game->id, 'type' => 'athlete', 'columns' => [],
        'zip_pattern' => '{association_code} {last_name}:{kind}']);
    $this->user = User::factory()->sta()->create();
    $this->user->givePermissionTo(StaPermission::ExportGameFiles->value);
});

/** @return array<string, string> file name => contents */
function zipContents(): array
{
    $paths = Storage::disk(config('filesystems.private'))->allFiles('exports');
    expect($paths)->toHaveCount(1)->and($paths[0])->toEndWith('/asian-indoor-games-athletes-files.zip');
    $tmp = tempnam(sys_get_temp_dir(), 'zt');
    file_put_contents($tmp, Storage::disk(config('filesystems.private'))->get($paths[0]));
    $zip = new ZipArchive;
    $zip->open($tmp);
    $out = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $out[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }
    $zip->close();
    unlink($tmp);
    ksort($out);

    return $out;
}

it('names files by the pattern, replaces bad characters, numbers duplicates and lists missing files', function () {
    (new BuildGameFilesZip($this->layout, $this->user, ['passport_scan_path']))->handle(app(GameFileExporter::class));

    expect(zipContents())->toBe([
        'BJF Hasan_passport.pdf' => 'ali-passport',
        'BJF Hasan_photo-2.jpg' => 'omar-photo',
        'BJF Hasan_photo.jpg' => 'ali-photo',
        'missing.txt' => "BJF-AT-0002 Omar Hasan: passport\n",
    ]);

    $notification = $this->user->notifications()->sole();
    expect(json_encode($notification->data, JSON_UNESCAPED_SLASHES))->toContain('Files ZIP ready')
        ->toContain('https://files.test/exports/');
    $log = Activity::where('description', 'game files ZIP exported')->sole();
    expect($log->causer_id)->toBe($this->user->id)->and($log->subject_id)->toBe($this->game->id)->and($log->event)->toBe('exported')
        ->and($log->properties->only(['layout', 'people', 'files', 'missing', 'pattern'])->all())
        ->toBe(['layout' => 'athlete', 'pattern' => '{association_code} {last_name}:{kind}', 'people' => 2, 'files' => 3, 'missing' => 1]);
});

it('includes WADA certificates only for users who may view medical data', function () {
    (new BuildGameFilesZip($this->layout, $this->user, ['wada_certificate_path']))->handle(app(GameFileExporter::class));
    expect(zipContents())->not->toHaveKey('BJF Hasan_wada.pdf')
        ->and(zipContents()['missing.txt'])->toStartWith("WADA certificates left out: they need \"View medical & health data\".\n");

    Storage::disk(config('filesystems.private'))->deleteDirectory('exports');
    $this->user->givePermissionTo(StaPermission::ViewMedical->value);
    (new BuildGameFilesZip($this->layout, $this->user->fresh(), ['wada_certificate_path']))->handle(app(GameFileExporter::class));
    expect(zipContents())->toHaveKey('BJF Hasan_wada.pdf');
});

it('makes safe, unique file names', function (string $pattern, array $expected) {
    $p = Person::factory()->make(['code' => 'BJF-AT-0001', 'first_name' => 'Ali', 'last_name' => 'Al/Hasan', 'passport_no' => 'BH1']);
    $used = [];
    $names = [
        BuildGameFilesZip::fileName($pattern, $p, $this->assoc, 'photo', 'jpg', $used),
        BuildGameFilesZip::fileName($pattern, $p, $this->assoc, 'photo', 'jpg', $used),
    ];
    expect($names)->toBe($expected);
})->with([
    'default' => ['{code}_{kind}', ['BJF-AT-0001_photo.jpg', 'BJF-AT-0001_photo-2.jpg']],
    'slash and colon' => ['{last_name}:{first_name}', ['Al_Hasan_Ali.jpg', 'Al_Hasan_Ali-2.jpg']],
    'passport' => ['{passport_no}', ['BH1.jpg', 'BH1-2.jpg']],
    'nothing left' => ['***', ['___.jpg', '___-2.jpg']],
    'only dots' => ['..', ['file.jpg', 'file-2.jpg']],
]);

it('never writes a path into the ZIP, whatever the names hold', function () {
    $this->ali->update(['last_name' => '../../evil', 'passport_no' => "AB/12\\34\x01"]);
    $this->layout->update(['zip_pattern' => '{last_name}{passport_no}']);
    (new BuildGameFilesZip($this->layout->fresh(), $this->user))->handle(app(GameFileExporter::class));

    $names = array_keys(zipContents());
    expect($names)->toContain('_.._evilAB_12_34_.jpg');
    foreach ($names as $name) {
        expect($name)->not->toContain('/')->not->toContain('\\')->not->toStartWith('.');
    }
});

it('refuses to build for a draft game or a requester who lost the permission', function () {
    $this->game->update(['published_at' => null]);
    (new BuildGameFilesZip($this->layout->fresh(), $this->user))->handle(app(GameFileExporter::class));

    $this->game->update(['published_at' => now()]);
    $this->user->revokePermissionTo(StaPermission::ExportGameFiles->value);
    (new BuildGameFilesZip($this->layout->fresh(), $this->user->fresh()))->handle(app(GameFileExporter::class));

    expect($this->disk->allFiles('exports'))->toBe([])
        ->and(Activity::where('description', 'game files ZIP exported')->exists())->toBeFalse()
        ->and($this->user->notifications()->count())->toBe(2)
        ->and(json_encode($this->user->notifications()->first()->data))->toContain('Files ZIP not built');
});

it('queues the ZIP from the game page for export_game_files users', function () {
    Bus::fake();
    actingInAdmin($this->user);
    livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->callAction('zip', ['type' => 'athlete', 'columns' => ['passport_scan_path', 'cpr_front_path']])
        ->assertNotified('Building the files ZIP');

    Bus::assertDispatched(BuildGameFilesZip::class, fn (BuildGameFilesZip $job) => $job->layout->is($this->layout)
        && $job->user->is($this->user) && $job->columns === ['passport_scan_path', 'cpr_front_path']);
});

it('hides the ZIP action once the game is a draft', function () {
    actingInAdmin($this->user);
    $page = livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])->assertActionVisible('zip');
    $this->game->update(['published_at' => null]);

    $page->call('$refresh')->assertActionHidden('zip');
});

it('refuses a direct download call once the permission is gone or the game is a draft', function () {
    actingInAdmin($this->user);
    $draft = livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()]);
    $revoked = livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()]);
    $this->game->update(['published_at' => null]);
    $draft->call('download', 'athlete')->assertForbidden();

    $this->game->update(['published_at' => now()]);
    $this->user->revokePermissionTo(StaPermission::ExportGameFiles->value);
    $revoked->call('download', 'athlete')->assertForbidden();
    expect(Activity::where('description', 'game system file exported')->exists())->toBeFalse();
});
