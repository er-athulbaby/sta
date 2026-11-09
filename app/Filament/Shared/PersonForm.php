<?php

namespace App\Filament\Shared;

use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Models\ClothingItem;
use App\Models\Person;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class PersonForm
{
    public const FUNCTIONS = ['Coach', 'Assistant Coach', 'Team Manager', 'Team Admin', 'Physio', 'Doctor', 'Other'];

    public static function configure(Schema $schema, bool $withAssociation = false): Schema
    {
        $isOfficial = fn (Get $get) => $get('type') === PersonType::Official->value;

        return $schema->components([
            Section::make('Person')->columns(2)->schema([
                ...($withAssociation ? [Select::make('association_id')->relationship('association', 'name')->searchable()->preload()->required()] : []),
                Select::make('type')->options(self::options(PersonType::cases()))->required()->live()->default(PersonType::Athlete->value),
                TextInput::make('first_name')->required()->maxLength(100),
                TextInput::make('second_name')->maxLength(100),
                TextInput::make('third_name')->maxLength(100),
                TextInput::make('last_name')->required()->maxLength(100),
                DatePicker::make('dob')->label('Date of birth')->required()->maxDate(now()),
                Select::make('gender')->options(self::options(PersonGender::cases()))->required(),
                TextInput::make('nationality')->required()->maxLength(100),
                TextInput::make('height_cm')->label('Height (cm)')->numeric()->minValue(50)->maxValue(250),
                TextInput::make('weight_kg')->label('Weight (kg)')->numeric()->minValue(20)->maxValue(250),
                Toggle::make('is_active')->default(true),
            ]),
            Section::make('Team official details')->columns(3)->visible($isOfficial)->schema([
                Select::make('function')->options(array_combine(self::FUNCTIONS, self::FUNCTIONS))->required($isOfficial),
                TextInput::make('phone')->label('Mobile')->tel()->maxLength(50),
                TextInput::make('email')->email()->maxLength(255),
            ]),
            Section::make('Documents')->columns(3)->schema([
                TextInput::make('passport_no')->label('Passport number')->required()->maxLength(30)
                    ->rule(fn (?Model $record) => self::uniqueDocument('passport_no_hash', 'passport', $record)),
                DatePicker::make('passport_expiry')->required(),
                self::upload('passport_scan_path', 'Passport scan', 'scans', ['application/pdf', 'image/jpeg', 'image/png'], 5120),
                TextInput::make('cpr_no')->label('CPR number')->maxLength(30)
                    ->rule(fn (?Model $record) => self::uniqueDocument('cpr_no_hash', 'CPR', $record)),
                DatePicker::make('cpr_expiry')->requiredWith('cpr_no'),
                self::upload('cpr_scan_path', 'CPR scan', 'scans', ['application/pdf', 'image/jpeg', 'image/png'], 5120),
                self::upload('photo_path', 'Photo', 'photos', ['image/jpeg', 'image/png'], 2048),
            ]),
            Section::make('Clothing sizes')->columns(3)
                ->schema(fn (Get $get) => self::sizeFields(PersonType::tryFrom((string) $get('type')) ?? PersonType::Athlete)),
        ]);
    }

    private static function upload(string $name, string $label, string $dir, array $types, int $maxKb): FileUpload
    {
        return FileUpload::make($name)->label($label)
            // image() (if wanted) must come before acceptedFileTypes(), or it widens the list to image/* (SVG).
            ->acceptedFileTypes($types)->maxSize($maxKb)
            ->disk(config('filesystems.private'))->directory($dir)->visibility('private');
    }

    private static function uniqueDocument(string $hashColumn, string $label, ?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($hashColumn, $label, $record) {
            if (filled($value) && Person::documentExists($hashColumn, (string) $value, $record?->getKey())) {
                $fail("This $label number is already registered in the system.");
            }
        };
    }

    private static function sizeFields(PersonType $type): array
    {
        return ClothingItem::active()->forType($type)->orderBy('name')->get()
            ->map(fn (ClothingItem $item) => Select::make("sizes.{$item->id}")->label($item->name)
                ->options(array_combine($item->sizes, $item->sizes)))
            ->all();
    }

    private static function options(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
