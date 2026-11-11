<?php

namespace Database\Seeders;

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Enums\PhaseStatus;
use App\Enums\Role;
use App\Enums\StaPermission;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Comment;
use App\Models\Entry;
use App\Models\Event;
use App\Models\ExportLayout;
use App\Models\ExtraField;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\Invitation;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Phase2SportTotal;
use App\Models\SavedReport;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Services\Reports\Builder\Registry;
use App\Services\Reports\Builder\ReportDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Local visual QA only (M5 task 14, U11): the canvas sample world (6th Asian Indoor & Martial Arts Games, BWF, BJF, ...) so each page
 * can be compared with docs/design/canvas/project/*.dc.html. Never called from DatabaseSeeder; refuses to run outside local/testing.
 * Run after migrate:fresh: php artisan db:seed --class=DemoSeeder. Password: env DEMO_PASSWORD, else PASSWORD (a test value).
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo-password-2026';

    /** code => [name, email, admin [name, email] or null, extra editors [[name, email]], active, [sport => [[event, gender, max], ...]]] */
    private const ASSOCIATIONS = [
        'BWF' => ['Bahrain Weightlifting Federation', 'info@bwf.example', ['Ahmed Saleh', 'a.saleh@bwf.example'], [['Omar Faisal', 'o.faisal@bwf.example']], true,
            ['Weightlifting' => [['Male 60kg', 'men', 1], ['Male 67kg', 'men', 1], ['Male 95kg', 'men', 1], ['Male 110kg', 'men', 1],
                ['Female 49kg', 'women', 1], ['Female 57kg', 'women', 1]]]],
        'BJF' => ['Bahrain Judo Federation', 'office@bjf.example', ['Yusuf Kamal', 'y.kamal@bjf.example'], [['Reem Hassan', 'r.hassan@bjf.example']], true,
            ['Judo' => [['-66kg', 'men', 2], ['-52kg', 'women', 2], ['Mixed Team', 'mixed', 1]]]],
        'BSF' => ['Bahrain Squash & Badminton Federation', 'contact@bsf.example', ['Huda Nasser', 'h.nasser@bsf.example'], [['Jassim Ali', 'j.ali@bsf.example']], true,
            ['Squash' => [['Male Singles', 'men', 2], ['Female Singles', 'women', 2]], 'Badminton' => [['Male Singles', 'men', 2], ['Mixed Doubles', 'mixed', 1]]]],
        'BKF' => ['Bahrain Karate Federation', 'info@bkf.example', null, [], true, ['Karate' => [['Male Kata', 'men', 2], ['Female Kata', 'women', 2]]]],
        'BCF' => ['Bahrain Chess Federation', 'chess@bcf.example', ['Nawaf Isa', 'n.isa@bcf.example'], [['Zainab Ali', 'z.ali@bcf.example']], true,
            ['Chess' => [['Rapid', 'mixed', 2], ['Blitz', 'mixed', 2]]]],
        'BTF' => ['Bahrain Taekwondo Federation', 'info@btf.example', ['Hussain Ebrahim', 'h.ebrahim@btf.example'], [], true,
            ['Taekwondo' => [['Male -58kg', 'men', 2], ['Female -49kg', 'women', 2]]]],
        'BEF' => ['Bahrain eSports Federation', 'hello@bef.example', null, [], false, ['eSports' => [['Open', 'mixed', 2]]]],
        'BBF' => ['Bahrain Billiards Federation', 'office@bbf.example', ['Majed Saeed', 'm.saeed@bbf.example'], [['Hasan Taher', 'h.taher@bbf.example']], true,
            ['Billiards & Snooker' => [['Snooker', 'men', 2]]]],
        'BAA' => ['Bahrain Athletics Association', 'info@baa.example', ['Salman Jaber', 's.jaber@baa.example'], [], true,
            ['Athletics' => [['Male 100m', 'men', 2], ['Female 100m', 'women', 2]]]],
        'BSW' => ['Bahrain Swimming Association', 'info@bsw.example', ['Fatima Radhi', 'f.radhi@bsw.example'], [], true,
            ['Swimming' => [['Male 50m Freestyle', 'men', 2], ['Female 50m Freestyle', 'women', 2]]]],
    ];

    /** Phase 3 state per association, as on the canvas game dashboard. */
    private const LONG_LIST = ['BWF' => 'draft', 'BJF' => 'awaiting', 'BSF' => 'draft', 'BKF' => null, 'BCF' => 'awaiting',
        'BTF' => 'draft', 'BEF' => null, 'BBF' => 'draft', 'BAA' => 'submitted', 'BSW' => 'submitted'];

    private const SHOWN_LOGINS = ['ali.haroona@sta.example', 'sara.almeer@sta.example', 'a.saleh@bwf.example', 'm.khalil@bwf.example'];

    private string $password;

    /** @var array<int, array<int, string>> */
    private array $logins = [];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('DemoSeeder is for local visual QA only (local/testing environments).');
        }
        $this->password = env('DEMO_PASSWORD') ?: self::PASSWORD;
        $this->call([PermissionSeeder::class]);

        $items = collect(['T-Shirt' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'Tracksuit' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'Suit' => ['44', '46', '48', '50', '52', '54', '56']])
            ->map(fn ($sizes, $name) => ClothingItem::updateOrCreate(['name' => $name], ['sizes' => $sizes, 'applies_to' => AppliesTo::Both, 'is_active' => true]));

        $sta = $this->user('Ali Haroona', 'ali.haroona@sta.example', Role::SuperAdmin);
        $staff = $this->user('Sara Almeer', 'sara.almeer@sta.example', Role::Sta);
        $staff->syncPermissions(array_map(fn ($p) => $p->value, StaPermission::cases()));

        // Games are looked up by name, so a second run updates instead of duplicating.
        $game = Game::updateOrCreate(['name' => '6th Asian Indoor & Martial Arts Games 2026'],
            ['start_date' => '2026-11-20', 'end_date' => '2026-11-30', 'reminder_days' => [7, 3, 0], 'published_at' => now()]);
        $game->clothingItems()->sync($items->pluck('id'));
        $game->phase(1)->update(['enabled' => false, 'status' => PhaseStatus::Draft]);
        $game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'opens_at' => now()->subDays(45), 'deadline' => now()->subDays(14)]);
        $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'opens_at' => now()->subDays(10), 'deadline' => now()->addDays(6)]);
        $game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Draft, 'opens_at' => null, 'deadline' => now()->addDays(20)]);

        $gcc = Game::updateOrCreate(['name' => 'GCC Youth Games 2027'],
            ['start_date' => '2027-02-10', 'end_date' => '2027-02-19', 'reminder_days' => [7, 3, 0], 'published_at' => now()]);
        $gcc->clothingItems()->sync($items->pluck('id'));
        $gcc->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'opens_at' => now()->subDays(30), 'deadline' => now()->subDays(14)]);
        $gcc->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'opens_at' => now()->subDays(10), 'deadline' => now()->addDays(5)]);

        $i = 0;
        foreach (self::ASSOCIATIONS as $code => [$name, $email, $admin, $editors, $active, $sports]) {
            $i++;
            $a = Association::updateOrCreate(['code' => $code], ['name' => $name, 'email' => $email,
                'phone' => '+973 1700 '.(1001 + $i * 1010), 'is_active' => $active]);
            $game->associations()->syncWithoutDetaching([$a->id]);
            if ($i <= 4) {
                $gcc->associations()->syncWithoutDetaching([$a->id]);
            }
            $adminUser = $admin ? $this->user($admin[0], $admin[1], Role::AssocAdmin, $a) : null;
            foreach ($editors as [$n, $e]) {
                $this->user($n, $e, Role::AssocEditor, $a);
            }
            if ($code === 'BWF') {
                $this->user('Mariam Khalil', 'm.khalil@bwf.example', Role::AssocEditor, $a);
            }
            if ($code === 'BKF') {
                Invitation::updateOrCreate(['association_id' => $a->id, 'email' => 'info@bkf.example'],
                    ['role' => Role::AssocAdmin, 'token_hash' => hash('sha256', 'demo-bkf-invite'), 'invited_by' => $sta->id, 'expires_at' => now()->addDays(7)]);
            }

            $p2 = Submission::for($game, $a, 2);
            foreach ($sports as $sportName => $events) {
                $sport = Sport::updateOrCreate(['association_id' => $a->id, 'name' => $sportName], ['is_active' => true]);
                foreach ([$game, $gcc] as $g) {
                    if (! $g->isInvited($a)) {
                        continue;
                    }
                    $gs = GameSport::updateOrCreate(['game_id' => $g->id, 'sport_id' => $sport->id], ['p2_max_men' => 4, 'p2_max_women' => 4, 'p2_max_total' => 8,
                        'p4_max_men' => 3, 'p4_max_women' => 3, 'p4_max_officials' => 2, 'dob_to' => '2011-12-31',
                        'entry_policy' => 'Each NOC may enter a maximum of eight (8) athletes, including four (4) male and four (4) female athletes.']);
                    foreach ($events as [$evName, $gender, $max]) {
                        $event = Event::updateOrCreate(['sport_id' => $sport->id, 'name' => $evName], ['gender' => $gender, 'is_active' => true]);
                        $ge = GameEvent::updateOrCreate(['game_id' => $g->id, 'event_id' => $event->id], ['game_sport_id' => $gs->id, 'p2_max_athletes' => $max]);
                        if ($g->is($game)) {
                            NumberEntry::updateOrCreate(['submission_id' => $p2->id, 'game_event_id' => $ge->id], ['count' => 1]);
                        }
                    }
                    if ($g->is($game)) {
                        Phase2SportTotal::updateOrCreate(['submission_id' => $p2->id, 'game_sport_id' => $gs->id], ['male' => 3, 'female' => 1, 'gold' => 1, 'silver' => 1, 'bronze' => 2]);
                    }
                }
            }
            $this->finalise($p2, $code, $game, $adminUser);

            if ($state = self::LONG_LIST[$code]) {
                $p3 = Submission::for($game, $a, 3);
                $this->longList($p3, $a, $items);
                match ($state) {
                    'awaiting' => $p3->update(['status' => SubmissionStatus::AwaitingAssocAdmin, 'submitted_by' => $adminUser?->id, 'submitted_at' => now()->subDay()]),
                    'submitted' => $this->finalise($p3, $code, $game, $adminUser),
                    default => null,
                };
                if ($code === 'BWF') {
                    $p3->update(['preloaded_at' => now()->subDays(3)]);
                    Comment::firstOrCreate(['submission_id' => $p3->id, 'user_id' => $staff->id], ['body' => 'Please double check the Male 60kg numbers against Phase 2.']);
                }
                if ($code === 'BTF') {
                    $p3->update(['deadline_override' => now()->addDays(9), 'deadline_change' => 'extended', 'deadline_note' => 'Extended after a request from the federation.']);
                }
            }
        }

        // Second game: a Phase 1 answer with a note, and BJF's Phase 2 already submitted.
        $bjf = Association::where('code', 'BJF')->sole();
        $p1 = Submission::for($gcc, $bjf, 1);
        $judo = GameSport::where('game_id', $gcc->id)->whereHas('sport', fn ($q) => $q->where('association_id', $bjf->id))->first();
        if ($judo) {
            InterestAnswer::updateOrCreate(['submission_id' => $p1->id, 'game_sport_id' => $judo->id], ['answer' => 'yes', 'note' => 'Youth squad only.']);
        }
        $this->finalise($p1, 'BJF', $gcc, null);
        $this->finalise(Submission::for($gcc, $bjf, 2), 'BJF', $gcc, null);

        ExtraField::firstOrCreate(['label' => 'Shirt name'], ['applies_to' => AppliesTo::Athlete, 'type' => ExtraFieldType::Text,
            'help_text' => 'Name printed on the team shirt.', 'required' => false, 'sensitive' => false, 'is_active' => true]);
        ExportLayout::firstOrCreate(['game_id' => $game->id, 'type' => 'athlete'], ['file_format' => 'xlsx', 'row_mode' => 'one_per_athlete', 'columns' => [
            ['source' => 'person.code', 'heading' => 'ID'], ['source' => 'person.last_name', 'heading' => 'SURNAME', 'format' => 'upper'],
            ['source' => 'person.dob', 'heading' => 'DOB', 'format' => 'd/m/Y'], ['source' => 'association.code', 'heading' => 'Member']]]);
        SavedReport::firstOrCreate(['name' => 'All athletes', 'created_by' => $sta->id], ['subject' => 'people', 'visibility' => 'shared',
            'definition' => ReportDefinition::fresh(Registry::get('people')), 'updated_by' => $sta->id]);
        $this->placeholderFiles();

        $this->command?->info('Demo logins (local only), password from DEMO_PASSWORD, default '.self::PASSWORD);
        $this->command?->table(['Name', 'Role', 'Email', 'Assoc.'], array_filter($this->logins, fn ($l) => in_array($l[2], self::SHOWN_LOGINS, true)));
    }

    private function user(string $name, string $email, Role $role, ?Association $a = null): User
    {
        $this->logins[] = [$name, $role->value, $email, $a?->code ?? ''];

        return User::updateOrCreate(['email' => $email],
            ['name' => $name, 'password' => $this->password, 'role' => $role, 'association_id' => $a?->id, 'is_active' => true]);
    }

    private function finalise(Submission $s, string $code, Game $game, ?User $admin): void
    {
        $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => $code.'-G'.str_pad((string) $game->id, 3, '0', STR_PAD_LEFT).'-P'.$s->phase.'-R1',
            'submitted_by' => $admin?->id, 'submitted_at' => now()->subDays(2), 'approved_by' => $admin?->id, 'approved_at' => now()->subDays(2)]);
    }

    /** Small placeholder files for the people's photo and scan paths, so photos and "View" links work in the demo. */
    private function placeholderFiles(): void
    {
        $disk = Storage::disk(config('filesystems.private'));
        // A 1×1 grey PNG and a one-page empty PDF.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGPo6OgAAAMEAYFL09IQAAAAAElFTkSuQmCC');
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        Person::withoutGlobalScopes()->get(array_keys(Person::FILE_COLUMNS))
            ->flatMap(fn (Person $p) => array_values($p->only(array_keys(Person::FILE_COLUMNS))))->filter()->unique()
            ->reject(fn (string $path) => $disk->exists($path))
            ->each(fn (string $path) => $disk->put($path, str_ends_with(strtolower($path), '.pdf') ? $pdf : $png));
    }

    /** BWF gets the canvas people; the others get two athletes and an official so lists and flags have content. */
    private function longList(Submission $s, Association $a, $items): void
    {
        $ids = $items->values()->pluck('id');
        $people = $a->code === 'BWF' ? [
            ['Yousif Abdulla', 'Al Mannai', PersonGender::Male, '2003-03-14', '2030-04-09', 'Male 60kg', ['S', 'M', '48']],
            ['Hamad Jassim', 'Buali', PersonGender::Male, '2005-07-02', '2029-06-18', 'Male 60kg', ['S', 'M', null]],
            ['Ali Redha', 'Isa', PersonGender::Male, '2001-01-19', '2026-11-25', 'Male 95kg', ['XL', 'XL', '54']],
            ['Sayed Mahmood', 'Hashim', PersonGender::Male, '1999-10-08', '2028-01-30', 'Male 110kg', ['XXL', 'XXL', '56']],
            ['Noor Khalid', 'Al Sayed', PersonGender::Female, '2006-05-27', '2027-02-14', 'Female 57kg', ['S', 'S', '44']],
        ] : [
            ['Ahmed', 'Isa '.$a->code, PersonGender::Male, '2002-02-04', '2030-01-01', null, ['M', 'M', '48']],
            ['Sara', 'Jaffar '.$a->code, PersonGender::Female, '2004-06-17', '2030-01-01', null, ['S', 'S', '44']],
        ];
        // Phase 2 says 1 in Male 60kg and the BWF Long List has 2: the canvas "1 differs from Phase 2" flag.
        $events = $s->game->gameEvents()->whereHas('gameSport.sport', fn ($q) => $q->where('association_id', $a->id))->with(['event', 'gameSport'])->get();
        foreach ($people as [$first, $last, $gender, $dob, $expiry, $eventName, $sizes]) {
            $p = Person::where(['association_id' => $a->id, 'first_name' => $first, 'last_name' => $last])->first()
                ?? Person::factory()->complete()->create(['association_id' => $a->id, 'first_name' => $first, 'last_name' => $last, 'full_name' => strtoupper("$first $last"),
                    'gender' => $gender, 'dob' => $dob, 'passport_expiry' => $expiry, 'passport_scan_path' => $last === 'Al Sayed' ? null : 'scans/demo.pdf']);
            $p->syncSizes($ids->combine($sizes)->all());
            $ge = $eventName ? $events->first(fn ($e) => $e->event->name === $eventName)
                : $events->first(fn ($e) => $e->event->gender->value === ($gender === PersonGender::Male ? 'men' : 'women'));
            if ($ge) {
                Entry::firstOrCreate(['submission_id' => $s->id, 'person_id' => $p->id, 'game_event_id' => $ge->id, 'game_sport_id' => $ge->game_sport_id]);
            }
        }
        $officials = $a->code === 'BWF' ? [['Ebrahim Ahmed', 'Sanad', 'Head Coach'], ['Fatema Hasan', 'Ali', 'Physio'], ['Khalid Yusuf', 'Murad', 'Team Manager']]
            : [['Coach', 'Karim '.$a->code, 'Head Coach']];
        foreach ($officials as [$first, $last, $function]) {
            $o = Person::where(['association_id' => $a->id, 'first_name' => $first, 'last_name' => $last])->first()
                ?? Person::factory()->official()->complete()->create(['association_id' => $a->id, 'type' => PersonType::Official,
                    'first_name' => $first, 'last_name' => $last, 'full_name' => strtoupper("$first $last"), 'function' => $function,
                    'gender' => $first === 'Fatema Hasan' ? PersonGender::Female : PersonGender::Male]);
            $o->syncSizes($ids->combine(['L', 'L', $last === 'Ali' ? null : '52'])->all());
            if ($gs = $events->first()?->gameSport) {
                Entry::firstOrCreate(['submission_id' => $s->id, 'person_id' => $o->id, 'game_event_id' => null, 'game_sport_id' => $gs->id]);
            }
        }
    }
}
