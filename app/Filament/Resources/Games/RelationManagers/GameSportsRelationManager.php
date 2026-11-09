<?php

namespace App\Filament\Resources\Games\RelationManagers;

use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Sport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GameSportsRelationManager extends RelationManager
{
    protected static string $relationship = 'gameSports';

    protected static ?string $title = 'Sports & events';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('sport_id')->label('Sport')->required()->searchable()->disabledOn('edit')
                ->options(fn (RelationManager $livewire) => Sport::query()->where('is_active', true)
                    ->whereIn('association_id', $livewire->getOwnerRecord()->associations()->pluck('associations.id'))
                    ->whereNotIn('id', $livewire->getOwnerRecord()->gameSports()->pluck('sport_id'))
                    ->with('association')->get()->mapWithKeys(fn (Sport $s) => [$s->id => "{$s->name} ({$s->association->code})"]))
                ->in(fn (RelationManager $livewire, ?GameSport $record) => $record ? [$record->sport_id] : Sport::query()
                    ->whereIn('association_id', $livewire->getOwnerRecord()->associations()->pluck('associations.id'))
                    ->where('is_active', true)->pluck('id')->all())
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, RelationManager $livewire) => $rule->where('game_id', $livewire->getOwnerRecord()->id)),
            Fieldset::make('Phase 2 – Entry by Number (athletes)')->columns(3)->schema([
                TextInput::make('p2_max_men')->label('Max men')->integer()->minValue(0),
                TextInput::make('p2_max_women')->label('Max women')->integer()->minValue(0),
                TextInput::make('p2_max_total')->label('Max total')->integer()->minValue(0),
            ]),
            Fieldset::make('Phase 4 – Short List')->columns(3)->schema([
                TextInput::make('p4_max_men')->integer()->minValue(0),
                TextInput::make('p4_max_women')->integer()->minValue(0),
                TextInput::make('p4_max_officials')->label('Max team officials')->integer()->minValue(0),
            ]),
            Fieldset::make('Age eligibility (optional)')->columns(2)->schema([
                DatePicker::make('dob_from')->label('Born on or after'),
                DatePicker::make('dob_to')->label('Born on or before')->afterOrEqual('dob_from'),
            ]),
            Textarea::make('entry_policy')->rows(3)->columnSpanFull(),
            Repeater::make('gameEvents')->relationship()->label('Events')->visibleOn('edit')->addable(false)->deletable(false)
                ->reorderable(false)->columns(3)->columnSpanFull()
                // Never create or delete game events here (deletes cascade to entries): only update known ones.
                ->saveRelationshipsUsing(function (Repeater $component, ?GameSport $record) {
                    foreach ($component->getState() as $key => $item) {
                        if (str_starts_with($key, 'record-')) {
                            $record->gameEvents()->find((int) substr($key, 7))?->update(['p2_max_athletes' => $item['p2_max_athletes'] ?? null]);
                        }
                    }
                })
                ->schema([
                    TextEntry::make('event')->state(fn (?GameEvent $record) => $record?->event?->name),
                    TextEntry::make('gender')->state(fn (?GameEvent $record) => $record?->event?->gender?->label()),
                    TextInput::make('p2_max_athletes')->label('Phase 2 max athletes')->integer()->minValue(0),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle('Sport')
            ->modifyQueryUsing(fn ($query) => $query->with('sport.association')->withCount('gameEvents'))
            ->columns([
                TextColumn::make('sport.name'),
                TextColumn::make('sport.association.code')->label('Association'),
                TextColumn::make('p2_max_men')->label('P2 men'),
                TextColumn::make('p2_max_women')->label('P2 women'),
                TextColumn::make('p2_max_total')->label('P2 total'),
                TextColumn::make('game_events_count')->label('Events'),
            ])
            ->headerActions([
                CreateAction::make()->after(function (GameSport $record) {
                    $this->addMissingEvents($record);
                }),
                Action::make('syncEvents')->label('Sync events')->icon(Heroicon::OutlinedArrowPath)
                    ->action(function () {
                        $this->getOwnerRecord()->gameSports->each(fn (GameSport $gs) => $this->addMissingEvents($gs));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (GameSport $r) => ! $r->game->submissions()->exists()),
            ]);
    }

    // Adds a game event for each active event of the sport that does not have one yet.
    private function addMissingEvents(GameSport $gs): void
    {
        $have = $gs->gameEvents()->pluck('event_id');
        foreach ($gs->sport->events()->where('is_active', true)->whereNotIn('id', $have)->orderBy('name')->get() as $event) {
            $gs->gameEvents()->create(['game_id' => $gs->game_id, 'event_id' => $event->id]);
        }
    }
}
