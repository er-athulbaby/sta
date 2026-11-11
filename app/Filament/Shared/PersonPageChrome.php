<?php

namespace App\Filament\Shared;

use App\Models\Person;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/** Athlete / official create and edit pages (canvas PlayerForm, OfficialForm): soft blue page, code and completeness in the header. */
trait PersonPageChrome
{
    public function getPageClasses(): array
    {
        return ['sta-bg-alt'];
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! ($this->record ?? null) instanceof Person || ! $this->record->exists) {
            return 'Fields marked * are required to save. Fields needed for the Long List can wait, but the person stays Incomplete until they are filled.';
        }

        return new HtmlString(view('filament.resources.people.person-subheading', ['person' => $this->record])->render());
    }
}
