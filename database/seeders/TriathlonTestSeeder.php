<?php

namespace Database\Seeders;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Enums\Role;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Event;
use App\Models\Person;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Dummy Triathlon association for a TEST server: 1 admin, 1 editor, 15 athletes, 5 team officials, all complete.
 * No factories (faker is a dev dependency), safe to re-run (looks people up by name).
 * Run: ALLOW_TEST_DATA=true TEST_DATA_PASSWORD='...' php artisan db:seed --class=TriathlonTestSeeder --force
 */
class TriathlonTestSeeder extends Seeder
{
    private const ATHLETES = [
        ['Ali', 'Hasan', 'M', '2001-04-12'], ['Mohamed', 'Jaffar', 'M', '2003-08-30'], ['Hussain', 'Abbas', 'M', '1999-01-05'],
        ['Yusuf', 'Khalil', 'M', '2004-11-21'], ['Ahmed', 'Mansoor', 'M', '2002-06-17'], ['Salman', 'Rashid', 'M', '2000-02-28'],
        ['Isa', 'Matar', 'M', '2005-09-09'], ['Hamad', 'Nooh', 'M', '2003-03-03'],
        ['Fatima', 'Ebrahim', 'F', '2002-05-14'], ['Maryam', 'Saleh', 'F', '2004-07-22'], ['Zainab', 'Ali', 'F', '2001-12-01'],
        ['Noora', 'Jassim', 'F', '2003-10-10'], ['Sara', 'Yousif', 'F', '2005-01-25'], ['Aisha', 'Hameed', 'F', '2000-08-08'],
        ['Layla', 'Fadhel', 'F', '2004-04-04'],
    ];

    private const OFFICIALS = [
        ['Khalid', 'Murad', 'M', 'Head Coach'], ['Jassim', 'Taher', 'M', 'Assistant Coach'], ['Huda', 'Radhi', 'F', 'Physiotherapist'],
        ['Abdulla', 'Sanad', 'M', 'Team Manager'], ['Reem', 'Buali', 'F', 'Team Doctor'],
    ];

    public function run(): void
    {
        if (app()->isProduction() && ! filter_var(env('ALLOW_TEST_DATA'), FILTER_VALIDATE_BOOLEAN)) {
            throw new \RuntimeException('Test data only: run with ALLOW_TEST_DATA=true on a test server.');
        }
        $password = env('TEST_DATA_PASSWORD');
        if (blank($password) || strlen($password) < 8) {
            throw new \RuntimeException('Set TEST_DATA_PASSWORD (8+ characters) for the dummy logins.');
        }

        $a = Association::updateOrCreate(['code' => 'BTRI'], ['name' => 'Bahrain Triathlon Federation',
            'email' => 'info@triathlon.example', 'phone' => '+973 1700 5555', 'is_active' => true]);
        $sport = Sport::updateOrCreate(['association_id' => $a->id, 'name' => 'Triathlon'], ['is_active' => true]);
        foreach ([['Male Individual', 'men'], ['Female Individual', 'women'], ['Mixed Relay', 'mixed']] as [$name, $gender]) {
            Event::updateOrCreate(['sport_id' => $sport->id, 'name' => $name], ['gender' => $gender, 'is_active' => true]);
        }

        foreach ([['Hassan Al Aali', 'admin@triathlon.example', Role::AssocAdmin], ['Mona Al Sayed', 'editor@triathlon.example', Role::AssocEditor]] as [$name, $email, $role]) {
            User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'role' => $role, 'association_id' => $a->id, 'is_active' => true]);
        }

        $items = ClothingItem::where('is_active', true)->get();
        $n = 0;
        foreach (self::ATHLETES as [$first, $last, $g, $dob]) {
            $this->person($a, PersonType::Athlete, $first, $last, $g, $dob, null, ++$n, $items);
        }
        foreach (self::OFFICIALS as [$first, $last, $g, $function]) {
            $this->person($a, PersonType::Official, $first, $last, $g, '1985-06-15', $function, ++$n, $items);
        }
        $this->placeholderFiles();

        $this->command?->info('Bahrain Triathlon Federation (BTRI): 15 athletes, 5 officials.');
        $this->command?->table(['Role', 'Email'], [['Association admin', 'admin@triathlon.example'], ['Association editor', 'editor@triathlon.example']]);
    }

    private function person(Association $a, PersonType $type, string $first, string $last, string $g, string $dob, ?string $function, int $n, $items): void
    {
        $no = str_pad((string) $n, 3, '0', STR_PAD_LEFT);
        $p = Person::withoutGlobalScopes()->where(['association_id' => $a->id, 'first_name' => $first, 'last_name' => $last])->first()
            ?? Person::create(['association_id' => $a->id, 'type' => $type, 'first_name' => $first, 'last_name' => $last,
                'full_name' => strtoupper("$first $last"), 'gender' => $g === 'M' ? PersonGender::Male : PersonGender::Female, 'dob' => $dob,
                'nationality' => 'Bahrain', 'passport_no' => 'BT'.$no.'4821', 'passport_issuing_country' => 'Bahrain',
                'passport_issue_date' => now()->subYears(2)->toDateString(), 'passport_expiry' => now()->addYears(4)->toDateString(),
                'cpr_no' => '9'.$no.'12345', 'cpr_expiry' => now()->addYears(3)->toDateString(),
                'photo_path' => 'photos/triathlon-demo.png', 'passport_scan_path' => 'scans/triathlon-demo.pdf',
                'height_cm' => $g === 'M' ? 178 : 165, 'weight_kg' => $g === 'M' ? 70 : 57, 'function' => $function,
                'phone' => '3300'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'whatsapp_same' => true, 'whatsapp' => '3300'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'email' => strtolower("$first.$last").'@triathlon.example', 'emergency_name' => 'Family contact', 'emergency_relationship' => 'Father',
                'emergency_phone' => '3911'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'is_active' => true]);
        $size = $g === 'M' ? ['L', '50', '43'] : ['S', '44', '38'];
        $p->syncSizes($items->mapWithKeys(fn (ClothingItem $i, int $k) => [$i->id => collect($i->sizes)->contains($size[$k % 3]) ? $size[$k % 3] : null])->all());
    }

    private function placeholderFiles(): void
    {
        $disk = Storage::disk(config('filesystems.private'));
        // The disk does not throw: check the write, so running as a user without storage access is reported.
        if (! $disk->put('photos/.write-check', '') || ! $disk->delete('photos/.write-check')) {
            throw new \RuntimeException('Cannot write to storage/app/private: run this seeder as the web server user (sudo -u www-data …).');
        }
        $disk->put('photos/triathlon-demo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGPo6OgAAAMEAYFL09IQAAAAAElFTkSuQmCC'));
        $disk->put('scans/triathlon-demo.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    }
}
