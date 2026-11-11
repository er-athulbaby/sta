<?php

namespace App\Filament\Portal\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Support\Collection;

/** Portal home (canvas menu "Home"): the same game cards as the Games page. */
class Home extends Dashboard
{
    protected string $view = 'filament.portal.pages.games';

    protected static ?string $title = 'Home';

    protected static ?string $navigationLabel = 'Home';

    public function getGamesProperty(): Collection
    {
        return Games::forTenant(Filament::getTenant());
    }
}
