<?php

use App\Enums\EventGender;
use App\Enums\ExtraFieldType;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\Pages\GameExportFiles;
use App\Models\Association;
use App\Models\Event;
use App\Models\ExportLayout;
use App\Models\ExtraField;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\GameFiles\ExportSources;
use App\Services\GameFiles\GameFileExporter;
use Filament\Notifications\Notification;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games', 'start_date' => '2026-12-01', 'end_date' => '2026-12-10']);
    $judoFed = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $karateFed = Association::factory()->create(['code' => 'BKF', 'name' => 'Bahrain Karate Federation']);
    $this->game->associations()->attach([$judoFed->id, $karateFed->id]);
    $sport = fn (Association $a, string $name) => GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $a->id, 'name' => $name])->id]);
    $event = fn (GameSport $gs, string $name) => GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => $name, 'gender' => EventGender::Men])->id]);
    $this->judo = $sport($judoFed, 'Judo');
    $m60 = $event($this->judo, 'Men -60kg');
    $m66 = $event($this->judo, 'Men -66kg');
    $karate = $sport($karateFed, 'Karate');
    $kumite = $event($karate, 'Kumite');
    $this->karateFed = $karateFed;
    $this->judoFed = $judoFed;

    $shirt = ExtraField::factory()->create(['label' => 'Shirt name']);
    $donor = ExtraField::factory()->create(['label' => 'Donor type', 'type' => ExtraFieldType::Choice, 'options' => ['A', 'B'], 'sensitive' => true]);
    $this->ali = Person::factory()->create(['association_id' => $judoFed->id, 'first_name' => 'Ali', 'last_name' => 'Hasan',
        'full_name' => 'Ali "The Lion" Hasan', 'passport_no' => 'BH1234567', 'allergies' => 'Peanuts', 'consent_media' => true]);
    $this->ali->syncExtraValues([$shirt->id => 'ali h', $donor->id => 'B']);
    $omar = Person::factory()->create(['association_id' => $judoFed->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $coach = Person::factory()->official()->create(['association_id' => $judoFed->id, 'function' => 'Head Coach']);
    $karim = Person::factory()->create(['association_id' => $karateFed->id, 'first_name' => 'Karim', 'last_name' => 'Ahmed',
        'full_name' => 'كريم أحمد', 'passport_no' => 'BH7654321']);

    $athlete = fn (Submission $s, Person $p, GameEvent $ge) => $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $ge->id, 'game_sport_id' => $ge->game_sport_id]);
    $official = fn (Submission $s, Person $p, GameSport $gs) => $s->entries()->create(['person_id' => $p->id, 'game_event_id' => null, 'game_sport_id' => $gs->id]);
    // Judo: Long List (Ali, Omar, coach) and Short List (Ali in two events, coach) both submitted.
    $p3 = Submission::for($this->game, $judoFed, 3);
    $this->p4 = Submission::for($this->game, $judoFed, 4); // created while Phase 3 is a draft: nothing preloaded
    $athlete($p3, $this->ali, $m60);
    $athlete($p3, $omar, $m60);
    $official($p3, $coach, $this->judo);
    $athlete($this->p4, $this->ali, $m60);
    $athlete($this->p4, $this->ali, $m66);
    $official($this->p4, $coach, $this->judo);
    // Karate: Long List only.
    $k3 = Submission::for($this->game, $karateFed, 3);
    $athlete($k3, $karim, $kumite);
    foreach ([$p3, $this->p4, $k3] as $s) {
        $s->update(['status' => SubmissionStatus::Submitted]);
    }

    $this->layout = ExportLayout::create(['game_id' => $this->game->id, 'type' => 'athlete', 'file_format' => 'xlsx', 'row_mode' => 'one_per_athlete', 'columns' => [
        ['source' => 'person.code', 'heading' => 'ID'],
        ['source' => 'person.last_name', 'heading' => 'SURNAME', 'format' => 'upper'],
        ['source' => 'person.dob', 'heading' => 'DOB', 'format' => 'd/m/Y'],
        ['source' => 'person.gender', 'heading' => 'Sex', 'format' => 'm_f'],
        ['source' => 'person.passport_no', 'heading' => 'Passport'],
        ['source' => 'entry.event', 'heading' => 'Events'],
        ['source' => 'game.age', 'heading' => 'Age'],
        ['source' => 'association.code', 'heading' => 'Member'],
        ['source' => 'text', 'heading' => 'NOC', 'value' => 'BRN'],
        ['source' => 'extra.shirt_name', 'heading' => 'Shirt', 'format' => 'title'],
        ['source' => 'extra.donor_type', 'heading' => 'Donor'],
        ['source' => 'person.allergies', 'heading' => 'Allergies'],
        ['source' => 'person.consent_media', 'heading' => 'Media', 'format' => 'y_n'],
    ]]);
    $this->exporter = User::factory()->sta()->create();
    $this->exporter->givePermissionTo(StaPermission::ExportGameFiles->value);
});

it('builds rows from each association\'s Short List, else its Long List, leaving medical columns out for a non-medical user', function () {
    $table = app(GameFileExporter::class)->table($this->layout, $this->exporter);

    expect($table['headings'])->toBe(['ID', 'SURNAME', 'DOB', 'Sex', 'Passport', 'Events', 'Age', 'Member', 'NOC', 'Shirt', 'Media'])
        ->and($table['left_out'])->toBe(['Donor', 'Allergies'])
        ->and($table['rows'])->toBe([
            ['BJF-AT-0001', 'HASAN', '10/03/2005', 'M', 'BH1234567', 'Men -60kg; Men -66kg', 21, 'BJF', 'BRN', 'Ali H', 'Y'],
            ['BKF-AT-0001', 'AHMED', '10/03/2005', 'M', 'BH7654321', 'Kumite', 21, 'BKF', 'BRN', null, 'N'],
        ]);

    $this->exporter->givePermissionTo(StaPermission::ViewMedical->value);
    $medic = app(GameFileExporter::class)->table($this->layout, $this->exporter->fresh());
    expect($medic['left_out'])->toBe([])->and(array_slice($medic['rows'][0], 10, 2))->toBe(['B', 'Peanuts']);
});

it('falls back to the Long List, splits rows per event and filters by association and sport', function () {
    $this->p4->update(['status' => SubmissionStatus::Draft]);
    $codes = fn (array $t) => array_column($t['rows'], 0);
    $exporter = app(GameFileExporter::class);

    expect($codes($exporter->table($this->layout, $this->exporter)))->toBe(['BJF-AT-0001', 'BJF-AT-0002', 'BKF-AT-0001']);

    $this->p4->update(['status' => SubmissionStatus::Submitted]);
    $this->layout->update(['row_mode' => 'one_per_event']);
    $perEvent = $exporter->table($this->layout->fresh(), $this->exporter);
    expect(array_map(fn ($r) => [$r[0], $r[5]], $perEvent['rows']))->toBe([
        ['BJF-AT-0001', 'Men -60kg'], ['BJF-AT-0001', 'Men -66kg'], ['BKF-AT-0001', 'Kumite'],
    ])->and($codes($exporter->table($this->layout, $this->exporter, $this->karateFed->id)))->toBe(['BKF-AT-0001'])
        ->and($codes($exporter->table($this->layout, $this->exporter, null, $this->judo->id)))->toBe(['BJF-AT-0001', 'BJF-AT-0001']);
});

it('exports team officials with the official layout', function () {
    $layout = ExportLayout::create(['game_id' => $this->game->id, 'type' => 'official', 'columns' => [
        ['source' => 'person.code', 'heading' => 'ID'], ['source' => 'person.function', 'heading' => 'Role'], ['source' => 'entry.sport', 'heading' => 'Sport'],
    ]]);

    expect(app(GameFileExporter::class)->table($layout, $this->exporter)['rows'])->toBe([['BJF-TO-0001', 'Head Coach', 'Judo']]);
});

it('writes CSV as UTF-8 with commas and proper quoting', function () {
    $this->layout->update(['file_format' => 'csv', 'columns' => [
        ['source' => 'person.full_name', 'heading' => 'Name, full'], ['source' => 'text', 'heading' => 'NOC', 'value' => 'BRN'],
    ]]);
    $exporter = app(GameFileExporter::class);
    [$path, $name] = $exporter->write($this->layout->fresh(), $exporter->table($this->layout->fresh(), $this->exporter));

    expect($name)->toBe('asian-indoor-games-athletes.csv')
        ->and(file_get_contents($path))->toBe("\"Name, full\",NOC\r\n\"Ali \"\"The Lion\"\" Hasan\",BRN\r\n\"كريم أحمد\",BRN\r\n");
    unlink($path);
});

it('downloads the upload file as Excel from the game page and audits it', function () {
    actingInAdmin($this->exporter);
    $page = livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->callAction('download', ['type' => 'athlete'])
        ->assertFileDownloaded('asian-indoor-games-athletes.xlsx')
        ->assertNotified('Some columns were left out');

    $path = sys_get_temp_dir().'/'.uniqid('gsf').'.xlsx';
    file_put_contents($path, base64_decode($page->effects['download']['content']));
    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    unlink($path);
    expect($rows[0][0])->toBe('ID')->and($rows[1][4])->toBe('BH1234567')->and(count($rows))->toBe(3);

    $log = Activity::where('description', 'game system file exported')->sole();
    expect($log->causer_id)->toBe($this->exporter->id)->and($log->subject_id)->toBe($this->game->id)
        ->and($log->properties->only(['layout', 'file', 'rows', 'left_out'])->all())
        ->toBe(['layout' => 'athlete', 'file' => 'asian-indoor-games-athletes.xlsx', 'rows' => 2, 'left_out' => ['Donor', 'Allergies']]);
});

it('asks for a layout before downloading', function () {
    actingInAdmin($this->exporter);
    livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->callAction('download', ['type' => 'official'])->assertNotified('Set up the official layout first.')->assertNoFileDownloaded();
});

it('never exports sports answered "no" in Phase 1', function () {
    $this->game->phases()->where('phase', 1)->update(['enabled' => true]);
    $p1 = Submission::for($this->game, $this->judoFed, 1);
    $p1->answers()->create(['game_sport_id' => $this->judo->id, 'answer' => 'no']);
    $p1->update(['status' => SubmissionStatus::Submitted]);

    expect(array_column(app(GameFileExporter::class)->table($this->layout, $this->exporter)['rows'], 0))->toBe(['BKF-AT-0001']);
});

it('guards CSV values against formulas but keeps +digits phone numbers', function () {
    $this->ali->update(['full_name' => '=HYPERLINK("http://x","y")', 'phone_cc' => '+973', 'phone' => '33445566']);
    $this->layout->update(['file_format' => 'csv', 'columns' => [
        ['source' => 'person.full_name', 'heading' => 'Name'], ['source' => 'person.phone', 'heading' => 'Mobile'],
        ['source' => 'text', 'heading' => '@Note', 'value' => '-1'],
    ]]);
    $exporter = app(GameFileExporter::class);
    [$path] = $exporter->write($this->layout->fresh(), $exporter->table($this->layout->fresh(), $this->exporter));

    expect(explode("\r\n", file_get_contents($path))[0])->toBe("Name,Mobile,'@Note")
        ->and(explode("\r\n", file_get_contents($path))[1])->toBe("\"'=HYPERLINK(\"\"http://x\"\",\"\"y\"\")\",+97333445566,'-1");
    unlink($path);
});

it('writes xlsx strings as literal text, never formulas', function () {
    $this->ali->update(['full_name' => '=HYPERLINK("http://x","y")']);
    $this->layout->update(['columns' => [['source' => 'person.full_name', 'heading' => 'Name']]]);
    $exporter = app(GameFileExporter::class);
    [$path, $name] = $exporter->write($this->layout->fresh(), $exporter->table($this->layout->fresh(), $this->exporter));

    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);
    expect($name)->toBe('asian-indoor-games-athletes.xlsx')->and($sheet)->not->toContain('<f>')->toContain('=HYPERLINK');
});

it('formats a malformed stored date as the raw value instead of failing', function () {
    expect(ExportSources::format('not-a-date', 'date', 'd/m/Y'))->toBe('not-a-date')
        ->and(ExportSources::format('2026-12-01', 'date', 'd/m/Y'))->toBe('01/12/2026');
});

it('hides the download once the game is a draft', function () {
    actingInAdmin($this->exporter);
    $page = livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])->assertActionVisible('download');
    $this->game->update(['published_at' => null]);

    $page->call('$refresh')->assertActionHidden('download');
});

it('notes columns whose source no longer exists apart from medical ones', function () {
    $this->layout->update(['columns' => [...$this->layout->columns, ['source' => 'extra.deleted_field', 'heading' => 'Gone']]]);
    $table = app(GameFileExporter::class)->table($this->layout->fresh(), $this->exporter);
    expect($table['left_out'])->toBe(['Donor', 'Allergies'])->and($table['gone'])->toBe(['Gone']);

    actingInAdmin($this->exporter);
    livewire(GameExportFiles::class, ['record' => $this->game->getRouteKey()])
        ->callAction('download', ['type' => 'athlete'])
        ->assertNotified(Notification::make()->warning()->title('Some columns were left out')
            ->body("They need \"View medical & health data\": Donor, Allergies\nTheir source no longer exists: Gone"));
    expect(Activity::where('description', 'game system file exported')->sole()->properties['gone'])->toBe(['Gone']);
});

it('exports a person without a gender as empty', function () {
    $raw = new ReflectionMethod(GameFileExporter::class, 'raw');
    $p = Person::factory()->make(['gender' => null]);

    expect($raw->invoke(app(GameFileExporter::class), ['source' => 'person.gender'], $p, $this->judoFed, collect(), $this->game, collect()))->toBeNull();
});
