<?php

namespace App\Filament\Resources\Games\Schemas;

use App\Filament\Shared\AttachmentActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Details')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(200)->columnSpanFull(),
                AttachmentActions::view(FileUpload::make('logo_path')->label('Game logo')->image()->acceptedFileTypes(['image/jpeg', 'image/png'])
                    ->maxSize(2048)->disk(config('filesystems.private'))->directory('game-logos')->visibility('private')),
                DatePicker::make('start_date')->required(),
                DatePicker::make('end_date')->required()->afterOrEqual('start_date'),
                TagsInput::make('reminder_days')->label('Reminder days before the deadline')->required()
                    ->nestedRecursiveRules(['integer', 'min:0', 'max:60'])
                    ->helperText('E.g. 7, 3, 0 — emails on those days before the deadline, then daily while overdue.')
                    ->dehydrateStateUsing(fn (array $state) => collect($state)->map(fn ($d) => (int) $d)->unique()->sortDesc()->values()->all()),
            ]),
            Section::make('Associations')->schema([
                CheckboxList::make('associations')->relationship('associations', 'name',
                    fn ($query) => $query->where('is_active', true)->orderBy('name'))->columns(3)->searchable()->bulkToggleable(),
            ]),
            Section::make('Clothing items collected for this game')->schema([
                CheckboxList::make('clothingItems')->relationship('clothingItems', 'name',
                    fn ($query) => $query->where('is_active', true)->orderBy('name'))->columns(3),
            ]),
        ]);
    }
}
