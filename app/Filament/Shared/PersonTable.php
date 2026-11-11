<?php

namespace App\Filament\Shared;

use App\Enums\PersonType;
use App\Models\ClothingItem;
use App\Models\Person;
use App\Support\InitialsAvatarProvider;
use App\Support\Sta;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PersonTable
{
    /** Delete exists only on the admin table (super admin only); the portal never deletes people. */
    public static function configure(Table $table, bool $withAssociation = false): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['fieldValues', 'sizes', ...($withAssociation ? ['association'] : [])])) // missingFields() and sizes per row
            ->deferFilters(false) // the canvas list applies its association select and "Expiring passports" toggle at once
            ->description('Passport dates in orange expire within 6 months.')
            ->columns([
                ImageColumn::make('photo_path')->label('')->circular()->disk(config('filesystems.private'))->visibility('private')
                    ->defaultImageUrl(fn (Person $record) => InitialsAvatarProvider::svg(Sta::initials($record->fullName()))),
                TextColumn::make('last_name')->label('Name')->formatStateUsing(fn (Person $record) => $record->fullName())
                    ->description(fn (Person $record) => $record->code)->weight(FontWeight::SemiBold)
                    // Canvas "Search by name or passport" / "Name, passport or CPR": document numbers match exactly (blind index).
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('passport_no_hash', Person::blindIndex($search))->orWhere('cpr_no_hash', Person::blindIndex($search))))
                    ->sortable(),
                TextColumn::make('code')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                ...($withAssociation ? [TextColumn::make('association.name')->searchable()] : []),
                TextColumn::make('type')->label('Type')
                    ->formatStateUsing(fn (PersonType $state, Person $record) => $state === PersonType::Official && filled($record->function) ? $record->function : $state->label()),
                TextColumn::make('dob')->date('d M Y')->label('Date of birth'),
                TextColumn::make('passport_expiry')->date('d M Y')->weight(FontWeight::SemiBold)
                    ->color(fn (Person $record) => $record->passport_expiry?->lt(now()->addMonths(6)) ? 'warning' : null),
                TextColumn::make('sizes_state')->label('Sizes')->badge()
                    ->state(fn (Person $record) => ($n = self::missingSizes($record)) ? "{$n} missing" : 'Complete')
                    ->color(fn (string $state) => $state === 'Complete' ? 'success' : 'warning'),
                TextColumn::make('completeness')->badge()
                    ->state(fn (Person $record) => $record->isComplete() ? 'Complete' : 'Incomplete')
                    ->color(fn (string $state) => $state === 'Complete' ? 'success' : 'warning')
                    ->tooltip(fn (Person $record) => $record->isComplete() ? null : 'Missing: '.implode(', ', $record->missingFields())),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options(collect(PersonType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                ...($withAssociation ? [
                    SelectFilter::make('association')->relationship('association', 'name'),
                    Filter::make('expiring')->label('Expiring passports')
                        ->query(fn (Builder $query) => $query->whereNotNull('passport_expiry')->where('passport_expiry', '<', now()->addMonths(6))),
                ] : []),
            ])
            ->recordActions([
                AttachmentActions::group(),
                EditAction::make(),
                ...($withAssociation ? [DeleteAction::make()->visible(fn () => (bool) auth()->user()?->isSuperAdmin())] : []),
            ]);
    }

    /** Active clothing items for the person's type that have no size yet (canvas Registry "Sizes" column). Item list is memoised per request. */
    public static function missingSizes(Person $record): int
    {
        if (! app()->bound('sta.clothing_items')) {
            app()->scoped('sta.clothing_items', fn () => ClothingItem::where('is_active', true)->get());
        }
        $have = $record->sizes->pluck('size', 'clothing_item_id');

        return app('sta.clothing_items')->filter(fn (ClothingItem $i) => $i->applies_to->includes($record->type) && blank($have->get($i->id)))->count();
    }
}
