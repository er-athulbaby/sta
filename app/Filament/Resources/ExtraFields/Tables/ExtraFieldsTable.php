<?php

namespace App\Filament\Resources\ExtraFields\Tables;

use App\Enums\AppliesTo;
use App\Enums\ExtraFieldType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExtraFieldsTable
{
    public static function configure(Table $table): Table
    {
        // No delete actions: extra fields are only deactivated.
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('key'),
                TextColumn::make('type')->formatStateUsing(fn (ExtraFieldType $state) => $state->label()),
                TextColumn::make('applies_to')->formatStateUsing(fn (AppliesTo $state) => $state->label()),
                IconColumn::make('required')->boolean()->label('Required to be complete'),
                IconColumn::make('sensitive')->boolean(),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
