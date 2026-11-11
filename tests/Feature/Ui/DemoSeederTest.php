<?php

use App\Models\Association;
use App\Models\Game;
use App\Models\Person;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake(config('filesystems.private')));

it('builds the canvas sample world', function () {
    $this->seed(DemoSeeder::class);

    expect(Game::count())->toBe(2)
        ->and(Association::count())->toBe(10)
        ->and(Person::withoutGlobalScopes()->where('last_name', 'Buali')->exists())->toBeTrue()
        ->and(Person::withoutGlobalScopes()->where(['first_name' => 'Fatema Hasan', 'last_name' => 'Ali'])->sole()->gender->value)->toBe('female')
        ->and(Submission::withoutGlobalScopes()->where('phase', 3)->count())->toBe(8)
        ->and(Submission::withoutGlobalScopes()->where('phase', 2)->where('status', 'submitted')->count())->toBe(11)
        ->and(User::where('email', 'ali.haroona@sta.example')->sole()->isSuperAdmin())->toBeTrue()
        ->and(User::where('email', 'a.saleh@bwf.example')->sole()->role->value)->toBe('assoc_admin');

    $main = Game::where('name', 'like', '6th%')->sole();
    expect($main->phase(1)->enabled)->toBeFalse()
        ->and($main->phase(2)->status->value)->toBe('closed')
        ->and($main->phase(3)->status->value)->toBe('open')
        ->and($main->phase(4)->status->value)->toBe('draft');

    // Placeholder photos and scans, so grid avatars and View links work in the demo.
    Storage::disk(config('filesystems.private'))->assertExists(['photos/x.jpg', 'scans/demo.pdf']);
});

it('is safe to run twice', function () {
    $this->seed(DemoSeeder::class);
    $people = Person::withoutGlobalScopes()->count();
    $this->seed(DemoSeeder::class);

    expect(Person::withoutGlobalScopes()->count())->toBe($people)->and(Game::count())->toBe(2);
});

it('refuses to run outside local and testing', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);
    expect(Game::count())->toBe(0);
});
