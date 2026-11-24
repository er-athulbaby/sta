<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Mrz\MrzReader;
use App\Services\Mrz\TesseractMrzReader;
use App\Support\Sta;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MrzReader::class, TesseractMrzReader::class);
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

        // One date format everywhere ("24 Nov 2026", Sta::DATE). The non-native picker shows it in any browser locale
        // (a native input shows mm/dd/yyyy in US browsers); the state stays Y-m-d. DatePicker extends DateTimePicker.
        DateTimePicker::configureUsing(fn (DateTimePicker $c) => $c->native(false)
            ->defaultDateDisplayFormat(Sta::DATE)->defaultDateTimeDisplayFormat(Sta::DATE_TIME)
            ->defaultDateTimeWithSecondsDisplayFormat(Sta::DATE_TIME.':s'));
        Table::configureUsing(fn (Table $t) => $t->defaultDateDisplayFormat(Sta::DATE)->defaultDateTimeDisplayFormat(Sta::DATE_TIME));
        Schema::configureUsing(fn (Schema $s) => $s->defaultDateDisplayFormat(Sta::DATE)->defaultDateTimeDisplayFormat(Sta::DATE_TIME));
    }
}
