<?php

namespace App\Providers;

use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(
            fn (User $user) => $user->isSuperAdmin() ? true : null
        );

        // Security: a string path in upload state is client-controlled; only the record's own stored paths are accepted.
        FileUpload::configureUsing(fn (FileUpload $component) => $component->preventFilePathTampering());
    }
}
