<?php

namespace App\Filament\Resources\Games\RelationManagers;

use App\Enums\PhaseStatus;
use App\Models\GamePhase;
use App\Notifications\PhaseOpened;
use App\Services\Phases\Recipients;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Notification;

class PhasesRelationManager extends RelationManager
{
    protected static string $relationship = 'phases';

    protected static ?string $title = 'Phases';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('enabled'),
            DateTimePicker::make('opens_at'),
            DateTimePicker::make('deadline')->after('opens_at')
                ->required(fn (?GamePhase $record) => $record?->status === PhaseStatus::Open),
        ]);
    }

    public function table(Table $table): Table
    {
        // No create/delete: the four phases are made with the game.
        return $table
            ->recordTitle('Phase')
            ->defaultSort('phase')
            ->columns([
                TextColumn::make('phase')->formatStateUsing(fn (int $state) => "Phase {$state} – ".GamePhase::NAMES[$state]),
                IconColumn::make('enabled')->boolean(),
                TextColumn::make('status')->badge()->color(fn (PhaseStatus $state) => match ($state) {
                    PhaseStatus::Draft => 'gray',
                    PhaseStatus::Open => 'success',
                    PhaseStatus::Closed => 'danger',
                }),
                TextColumn::make('opens_at')->dateTime('d M Y H:i', 'Asia/Bahrain'),
                TextColumn::make('deadline')->dateTime('d M Y H:i', 'Asia/Bahrain'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('open')->label('Open')->icon(Heroicon::OutlinedLockOpen)->requiresConfirmation()
                    ->visible(fn (GamePhase $r) => $r->enabled && $r->deadline !== null && $r->status !== PhaseStatus::Open)
                    ->action(function (GamePhase $r) {
                        $r->update(['status' => PhaseStatus::Open, 'opens_at' => $r->opens_at ?? now()]);
                        foreach ($r->game->associations as $association) {
                            Notification::send(Recipients::association($association), new PhaseOpened($r));
                        }
                    }),
                Action::make('close')->label('Close')->icon(Heroicon::OutlinedLockClosed)->color('danger')->requiresConfirmation()
                    ->visible(fn (GamePhase $r) => $r->status === PhaseStatus::Open)
                    ->action(fn (GamePhase $r) => $r->update(['status' => PhaseStatus::Closed])),
            ]);
    }
}
