<?php

namespace App\Filament\Resources\ExtraFields\Schemas;

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use App\Models\ExtraField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ExtraFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        $isChoice = fn (Get $get) => in_array($get('type'), [ExtraFieldType::Choice->value, ExtraFieldType::Choice], true);
        $hasValues = fn (?ExtraField $record) => (bool) $record?->values()->exists();

        return $schema->components([
            TextInput::make('label')->required()->maxLength(100),
            TextInput::make('key')->disabled()->dehydrated(false)->visibleOn('edit')
                ->helperText('Generated from the label. Used as the Excel import column and in export layouts.'),
            Select::make('applies_to')->options(collect(AppliesTo::cases())->mapWithKeys(fn ($a) => [$a->value => $a->label()]))
                ->required()->default(AppliesTo::Both->value),
            Select::make('type')->options(collect(ExtraFieldType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                ->required()->live()->default(ExtraFieldType::Text->value)->disabled($hasValues)
                ->helperText(fn (?ExtraField $record) => $hasValues($record) ? 'People already have values, so the type cannot change.' : null),
            TagsInput::make('options')->reorderable()->visible($isChoice)->required($isChoice)
                ->helperText('Type an option and press Enter. Order is kept.'),
            TextInput::make('help_text')->maxLength(255),
            Toggle::make('required')->label('Required to be complete'),
            Toggle::make('sensitive')->helperText('Hidden from STA users without "View medical & health data".'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }
}
