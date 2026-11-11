<?php

namespace App\Filament\Portal\Concerns;

use Illuminate\Contracts\View\View;

/** Phase 1 and 2 pages (portal and STA edit): the canvas Phase2.dc.html header band instead of Filament's header. */
trait CanvasPhaseHeader
{
    public function getHeader(): ?View
    {
        return view('filament.portal.partials.phase-header', ['title' => $this->phaseHeadingTitle()]);
    }
}
