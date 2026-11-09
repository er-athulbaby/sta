<?php

namespace App\Filament\Shared;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

final class PhoneField
{
    /** Columns: {$column}_cc and {$column}. $label goes on the number input (a closure may inject Get). */
    public static function make(string $column, string|Closure $label): Grid
    {
        return Grid::make(['default' => 3])->schema([
            Select::make($column.'_cc')->label('Code')->options(config('phone_codes'))->default('+973')->searchable()->required(),
            TextInput::make($column)->label($label)->tel()->regex('/^[0-9 ]{4,20}$/')->maxLength(20)->columnSpan(2),
        ])->columnSpan(1);
    }
}
