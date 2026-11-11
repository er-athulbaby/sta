<?php

namespace App\Filament\Shared;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

final class PhoneField
{
    /**
     * Columns: {$column}_cc and {$column}. Canvas: a 92px native code select beside the number; $label goes on the number
     * input (a closure may inject Get), the code select is labelled $codeLabel for screen readers ("Mobile country code").
     * $number may configure the input.
     */
    public static function make(string $column, string|Closure $label, ?Closure $number = null, string $codeLabel = 'Country code'): Grid
    {
        $codes = array_keys(config('phone_codes'));
        $input = TextInput::make($column)->label($label)->tel()->regex('/^[0-9 ]{4,20}$/')->maxLength(20);

        return Grid::make(1)->extraAttributes(['class' => 'sta-phone'])->schema([
            Select::make($column.'_cc')->label($codeLabel)->hiddenLabel()->options(array_combine($codes, $codes))->default('+973')
                ->required()->selectablePlaceholder(false),
            $number ? $number($input) : $input,
        ]);
    }
}
