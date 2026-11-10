<?php

namespace App\Filament\Shared;

use App\Enums\PersonType;
use App\Models\Person;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PersonTable
{
    /** Delete exists only on the admin table (super admin only); the portal never deletes people. */
    public static function configure(Table $table, bool $withAssociation = false): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('fieldValues')) // missingFields() per row
            ->columns([
                ImageColumn::make('photo_path')->label('')->circular()->disk(config('filesystems.private'))->visibility('private'),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('completeness')->badge()
                    ->state(fn (Person $record) => $record->isComplete() ? 'Complete' : 'Incomplete')
                    ->color(fn (string $state) => $state === 'Complete' ? 'success' : 'warning')
                    ->tooltip(fn (Person $record) => $record->isComplete() ? null : 'Missing: '.implode(', ', $record->missingFields())),
                TextColumn::make('last_name')->label('Name')->formatStateUsing(fn (Person $record) => $record->fullName())
                    ->searchable(['first_name', 'last_name'])->sortable(),
                ...($withAssociation ? [TextColumn::make('association.name')->searchable()] : []),
                TextColumn::make('type')->formatStateUsing(fn (PersonType $state) => $state->label())->badge(),
                TextColumn::make('function'),
                TextColumn::make('dob')->date('d M Y')->label('Date of birth'),
                TextColumn::make('passport_expiry')->date('d M Y')
                    ->color(fn (Person $record) => $record->passport_expiry->lt(now()->addMonths(6)) ? 'danger' : null),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options(collect(PersonType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                ...($withAssociation ? [SelectFilter::make('association')->relationship('association', 'name')] : []),
            ])
            ->recordActions([
                AttachmentActions::group(),
                EditAction::make(),
                ...($withAssociation ? [DeleteAction::make()->visible(fn () => (bool) auth()->user()?->isSuperAdmin())] : []),
            ]);
    }
}
