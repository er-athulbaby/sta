<?php

namespace App\Filament\Resources\Sports\RelationManagers;

use App\Enums\EventGender;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                    ->where('sport_id', $this->getOwnerRecord()->getKey())
                    ->where('gender', $get('gender'))),
            Select::make('gender')->options(collect(EventGender::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()]))->required(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        // No delete actions: events are only deactivated (BR-16).
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('gender')->formatStateUsing(fn (EventGender $state) => $state->label())->badge()
                    ->color(fn (EventGender $state) => match ($state) {
                        EventGender::Men => 'info', EventGender::Women => 'pink', EventGender::Mixed => 'gray'
                    }),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()]);
    }
}
