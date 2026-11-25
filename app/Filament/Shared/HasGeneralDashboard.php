<?php

namespace App\Filament\Shared;

use App\Support\GeneralDashboard;
use Filament\Facades\Filament;
use Filament\Support\Components\Attributes\Unexposed;
use Livewire\Attributes\Url;

/** The "Games dashboard" / "General dashboard" tabs of the admin home and the portal home; the tab stays in the query string. */
trait HasGeneralDashboard
{
    #[Url]
    public string $tab = 'games';

    /** STA only: the Association select (null = all). The portal is always scoped to its tenant. */
    public ?int $genAssociation = null;

    public string $genType = 'all';

    public ?int $genGame = null;

    private ?GeneralDashboard $general = null;

    #[Unexposed]
    public function general(): GeneralDashboard
    {
        $portal = Filament::getCurrentPanel()?->getId() === 'portal';
        // Fail closed: a portal request without a tenant never falls back to "all associations".
        $association = $portal ? ((int) Filament::getTenant()?->getKey() ?: abort(403)) : $this->genAssociation;

        return $this->general ??= new GeneralDashboard($association, $this->genType, $this->genGame);
    }
}
