<?php

namespace App\Filament\Shared;

use Filament\Actions\Action;

/** Stub until Task 10 (passport reading). */
final class ReadPassportAction
{
    public static function make(): Action
    {
        return Action::make('readPassport')->label('Read details from passport')->hidden();
    }
}
