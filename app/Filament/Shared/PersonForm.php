<?php

namespace App\Filament\Shared;

use App\Enums\ExtraFieldType;
use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Models\ClothingItem;
use App\Models\ExtraField;
use App\Models\Person;
use App\Models\PersonInjury;
use App\Models\PersonVisa;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use WeakMap;

final class PersonForm
{
    public const FUNCTIONS = ['Head of Delegation', 'Team Manager', 'Administrator', 'Head Coach', 'Assistant Coach', 'Goalkeeper Coach',
        'Fitness Coach', 'Performance Analyst', 'Team Doctor', 'Physiotherapist', 'Massage Therapist', 'Kit Manager', 'Media Officer',
        'Security Officer', 'Interpreter', 'Other'];

    /** @var ?WeakMap<object, array<int, string>> Livewire component => paths it stored from temporary uploads this request */
    private static ?WeakMap $freshUploads = null;

    public static function configure(Schema $schema, bool $withAssociation = false, PersonType $type = PersonType::Athlete, bool $lockType = false): Schema
    {
        $isOfficial = fn (Get $get) => $get('type') === PersonType::Official->value;
        $isAthlete = fn (Get $get) => $get('type') !== PersonType::Official->value;
        $medical = fn () => Person::canViewMedical(auth()->user());
        $countries = array_combine(array_values(config('countries')), array_values(config('countries')));
        $scanTypes = self::SCAN_TYPES;

        return $schema->components([
            Section::make('Person')->extraAttributes(['class' => 'sta-band sta-band-blue'])->icon(Heroicon::OutlinedUser)->columns(2)->schema([
                ...($withAssociation ? [Select::make('association_id')->relationship('association', 'name')->searchable()->preload()->required()] : []),
                Select::make('type')->options(self::options(PersonType::cases()))->required()->live()->default($type->value)->disabled($lockType)->dehydrated(),
                TextInput::make('full_name')->label('Full name (as in passport)')->maxLength(200)->columnSpanFull(),
                TextInput::make('first_name')->required()->maxLength(100),
                TextInput::make('second_name')->maxLength(100),
                TextInput::make('third_name')->maxLength(100),
                TextInput::make('last_name')->required()->maxLength(100),
                DatePicker::make('dob')->label('Date of birth')->required()->maxDate(now()),
                Select::make('gender')->options(self::options(PersonGender::cases()))->required(),
                Select::make('nationality')->options($countries)->searchable()->required(),
                TextInput::make('height_cm')->label('Height (cm)')->numeric()->minValue(50)->maxValue(250),
                TextInput::make('weight_kg')->label('Weight (kg)')->numeric()->minValue(20)->maxValue(250),
                self::upload('photo_path', 'Photo', 'photos', self::PHOTO_TYPES, 2048),
                Toggle::make('is_active')->default(true),
            ]),
            Section::make('Passport')->extraAttributes(['class' => 'sta-band sta-band-indigo'])->icon(Heroicon::OutlinedIdentification)->columns(3)->schema([
                self::upload('passport_scan_path', 'Passport scan', 'scans', $scanTypes, 5120)->columnSpanFull()
                    ->hintAction(ReadPassportAction::make()),
                TextInput::make('passport_no')->label('Passport number')->required()->maxLength(30)
                    ->rule(fn (?Model $record) => self::uniqueDocument('passport_no_hash', 'passport', $record)),
                Select::make('passport_issuing_country')->label('Issuing country')->options($countries)->searchable(),
                DatePicker::make('passport_issue_date')->label('Issue date')->maxDate(now()),
                DatePicker::make('passport_expiry')->label('Expiry date')->required(),
            ]),
            Section::make('CPR')->extraAttributes(['class' => 'sta-band sta-band-violet'])->icon(Heroicon::OutlinedCreditCard)->columns(3)->schema([
                TextInput::make('cpr_no')->label('CPR number')->maxLength(30)
                    ->rule(fn (?Model $record) => self::uniqueDocument('cpr_no_hash', 'CPR', $record)),
                DatePicker::make('cpr_expiry')->requiredWith('cpr_no'),
                self::upload('cpr_front_path', 'CPR front', 'scans', $scanTypes, 5120),
                self::upload('cpr_back_path', 'CPR back', 'scans', $scanTypes, 5120),
                self::upload('cpr_reader_path', 'CPR reader copy', 'scans', $scanTypes, 5120),
            ]),
            Section::make('Contact')->extraAttributes(['class' => 'sta-band sta-band-teal'])->icon(Heroicon::OutlinedPhone)->columns(2)->schema([
                // The label sits on the number input; a label on the wrapping Grid would not render.
                PhoneField::make('phone', fn (Get $get) => $isOfficial($get) ? 'Mobile' : 'Player Mobile'),
                Toggle::make('whatsapp_same')->label('WhatsApp is the same as mobile')->live(),
                PhoneField::make('whatsapp', 'WhatsApp')->hidden(fn (Get $get) => (bool) $get('whatsapp_same')),
                TextInput::make('email')->label(fn (Get $get) => $isOfficial($get) ? 'Email' : 'Player Email')->email()->maxLength(255),
                TextInput::make('address')->label('Player Address')->maxLength(255)->visible($isAthlete),
                TextInput::make('emergency_name')->label('Emergency contact name')->maxLength(200),
                TextInput::make('emergency_relationship')->label('Relationship')->maxLength(100),
                PhoneField::make('emergency_phone', 'Emergency phone'),
            ]),
            Section::make('Guardian')->extraAttributes(['class' => 'sta-band sta-band-amber'])->icon(Heroicon::OutlinedUserGroup)->description('For athletes under 18')->columns(2)->visible($isAthlete)->schema([
                TextInput::make('guardian_name')->maxLength(200),
                Select::make('guardian_relationship')->options(array_combine($g = ['Father', 'Mother', 'Legal guardian'], $g)),
                PhoneField::make('guardian_phone', 'Guardian phone'),
                TextInput::make('guardian_email')->email()->maxLength(255),
            ]),
            Section::make('Team official details')->extraAttributes(['class' => 'sta-band sta-band-cyan'])->icon(Heroicon::OutlinedBriefcase)->columns(2)->visible($isOfficial)->schema([
                Select::make('function')->options(array_combine(self::FUNCTIONS, self::FUNCTIONS))->required($isOfficial),
                TextInput::make('languages')->label('Languages spoken')->maxLength(255),
                Repeater::make('visas')->relationship()->columnSpanFull()->defaultItems(0)->addActionLabel('Add visa')->columns(3)->schema([
                    Select::make('country')->options($countries)->searchable()->required(),
                    Select::make('status')->options(array_combine(PersonVisa::STATUSES, PersonVisa::STATUSES))->required(),
                    TextInput::make('visa_no')->label('Visa number')->maxLength(50),
                    DatePicker::make('valid_until'),
                    self::trustFreshUploads(self::upload('copy_path', 'Visa copy', 'visas', $scanTypes, 5120))->columnSpan(2),
                ]),
            ]),
            Section::make('Clothing')->extraAttributes(['class' => 'sta-band sta-band-slate'])->icon(Heroicon::OutlinedShoppingBag)->columns(3)->schema([
                Select::make('fit')->options(["Men's" => 'Male', "Women's" => 'Female', 'Youth' => 'Youth']),
                TextInput::make('chest_cm')->label('Chest (cm)')->numeric()->minValue(40)->maxValue(200),
                TextInput::make('waist_cm')->label('Waist (cm)')->numeric()->minValue(40)->maxValue(200),
                Grid::make(3)->columnSpanFull()
                    ->schema(fn (Get $get) => self::sizeFields(PersonType::tryFrom((string) $get('type')) ?? PersonType::Athlete)),
            ]),
            Section::make('Additional information')->extraAttributes(['class' => 'sta-band sta-band-slate'])->icon(Heroicon::OutlinedAdjustmentsHorizontal)->hidden(fn (Get $get) => self::extraFields($get)->isEmpty())->schema([
                Grid::make(2)->schema(fn (Get $get) => self::extraFields($get)->map(fn (ExtraField $f) => self::extraInput($f))->all()),
            ]),
            // One section for both types (field names must be unique in a schema); title and type-only fields switch on type.
            // The whole section is left out of the schema (->hidden, not just invisible) for STA users without view_medical.
            Section::make(fn (Get $get) => $isOfficial($get) ? 'Health' : 'Medical')->extraAttributes(['class' => 'sta-band sta-band-rose'])->icon(Heroicon::OutlinedHeart)->columns(2)->hidden(fn () => ! $medical())->schema([
                Select::make('blood_group')->options(array_combine($b = ['A+', 'A−', 'B+', 'B−', 'AB+', 'AB−', 'O+', 'O−', 'Unknown'], $b)),
                Select::make('meal_requirement')->visible($isOfficial)
                    ->options(array_combine($m = ['Halal', 'Vegetarian', 'Vegan', 'Gluten-free', 'No restriction'], $m)),
                DatePicker::make('medical_clearance_expiry')->visible($isAthlete),
                TextInput::make('insurance_policy_no')->label('Insurance policy number')->maxLength(100)->visible($isAthlete),
                self::upload('wada_certificate_path', 'WADA certificate', 'scans', $scanTypes, 5120)->visible($isAthlete),
                Textarea::make('allergies')->rows(2),
                Textarea::make('chronic_conditions')->label(fn (Get $get) => $isOfficial($get) ? 'Medical conditions' : 'Chronic conditions')->rows(2),
                Textarea::make('medication')->label('Current medication')->rows(2),
                // Filament loads relationships even for hidden components; load nothing without view_medical.
                Repeater::make('injuries')->relationship(modifyQueryUsing: fn (Builder $query) => $medical() ? $query : $query->whereRaw('1 = 0'))->visible($isAthlete)->columnSpanFull()->defaultItems(0)->addActionLabel('Add injury')->columns(3)->schema([
                    DatePicker::make('injury_date')->required(),
                    Select::make('status')->options(array_combine(PersonInjury::STATUSES, PersonInjury::STATUSES))->required(),
                    Textarea::make('description')->required()->rows(2)->columnSpanFull(),
                ]),
            ]),
            Section::make('Consents')->extraAttributes(['class' => 'sta-band sta-band-fuchsia'])->icon(Heroicon::OutlinedShieldCheck)->visible($isAthlete)->schema([
                Toggle::make('consent_data')->label('Consent to store personal data'),
                Toggle::make('consent_media')->label('Consent to use photos in media'),
                Toggle::make('consent_medical')->label('Consent for emergency medical treatment'),
            ]),
            Section::make('Declarations')->extraAttributes(['class' => 'sta-band sta-band-fuchsia'])->icon(Heroicon::OutlinedShieldCheck)->visible($isOfficial)->schema([
                Toggle::make('decl_conduct')->label('Accepts the delegation code of conduct'),
                Toggle::make('decl_antidoping')->label('Will follow anti-doping and integrity rules'),
                Toggle::make('decl_data_sharing')->label('Consents to data being shared with organisers, airline and hotel'),
            ]),
        ]);
    }

    public const PHOTO_TYPES = ['image/jpeg', 'image/png'];

    public const SCAN_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    /**
     * The registry rules for the fields of the Long List side panel's short form (canvas LongList.dc.html), keyed
     * "{$prefix}field": the same requirements, lengths, option lists, document uniqueness and upload limits as configure().
     *
     * @return array<string, array<int, mixed>>
     */
    public static function shortFormRules(PersonType $type, string $prefix = '', string $uploadPrefix = ''): array
    {
        $official = $type === PersonType::Official;
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(PersonGender::class)],
            'dob' => ['required', 'date', 'before_or_equal:today'],
            'nationality' => ['required', Rule::in(array_values(config('countries')))],
            'passport_no' => ['required', 'string', 'max:30', self::uniqueDocument('passport_no_hash', 'passport', null)],
            'passport_expiry' => ['required', 'date'],
            'cpr_no' => ['nullable', 'string', 'max:30', self::uniqueDocument('cpr_no_hash', 'CPR', null)],
            'cpr_expiry' => ['nullable', 'date', 'required_with:'.$prefix.'cpr_no'],
            ...($official ? [
                'function' => ['required', Rule::in(self::FUNCTIONS)],
                'phone' => ['nullable', 'regex:/^[0-9 ]{4,20}$/', 'max:20'],
                'email' => ['nullable', 'email', 'max:255'],
            ] : []),
        ];
        foreach (self::sizeItems($type) as $item) {
            $rules["sizes.{$item->id}"] = ['nullable', Rule::in($item->sizes)];
        }
        $rules = collect($rules)->mapWithKeys(fn ($r, $field) => [$prefix.$field => $r])->all();

        return [...$rules,
            $uploadPrefix.'Photo' => ['nullable', 'file', 'mimetypes:'.implode(',', self::PHOTO_TYPES), 'max:2048'],
            $uploadPrefix.'PassportScan' => ['nullable', 'file', 'mimetypes:'.implode(',', self::SCAN_TYPES), 'max:5120'],
            $uploadPrefix.'CprScan' => ['nullable', 'file', 'mimetypes:'.implode(',', self::SCAN_TYPES), 'max:5120'],
        ];
    }

    /** @return \Illuminate\Support\Collection<int, ClothingItem> the size fields of a person type, in form order */
    public static function sizeItems(PersonType $type): \Illuminate\Support\Collection
    {
        return ClothingItem::active()->forType($type)->orderBy('name')->get();
    }

    private static function upload(string $name, string $label, string $dir, array $types, int $maxKb): FileUpload
    {
        return AttachmentActions::view(FileUpload::make($name)->label($label)
            // image() (if wanted) must come before acceptedFileTypes(), or it widens the list to image/* (SVG).
            ->acceptedFileTypes($types)->maxSize($maxKb)
            ->disk(config('filesystems.private'))->directory($dir)->visibility('private')->downloadable());
    }

    /**
     * A relationship Repeater re-validates each item after the form has already stored its uploads, so the tamper
     * guard would reject the item's own new path. Trust only paths stored from a temporary upload by this Livewire
     * component in this request (the schema is rebuilt mid-save, so the list is keyed by the component, not a closure).
     */
    // ponytail: replaces Filament's internal beforeStateDehydrated for visa copy uploads. Re-check against vendor/filament/forms
    // BaseFileUpload on every Filament upgrade; the visa upload tests fail if it breaks.
    private static function trustFreshUploads(FileUpload $upload): FileUpload
    {
        return $upload
            ->beforeStateDehydrated(function (FileUpload $component) {
                $temporary = array_filter(Arr::wrap($component->getRawState()), fn ($f) => $f instanceof TemporaryUploadedFile);
                $component->saveUploadedFiles();
                $livewire = $component->getLivewire();
                self::$freshUploads ??= new WeakMap;
                self::$freshUploads[$livewire] = [...self::$freshUploads[$livewire] ?? [],
                    ...array_values(array_intersect_key(Arr::wrap($component->getRawState()), $temporary))];
            }, shouldUpdateValidatedStateAfter: true)
            ->preventFilePathTampering(allowFilePathUsing: fn (string $file, FileUpload $component) => in_array($file, self::$freshUploads[$component->getLivewire()] ?? [], true));
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
        return self::sizeItems($type)
            ->map(fn (ClothingItem $item) => Select::make("sizes.{$item->id}")->label($item->name)
                ->options(array_combine($item->sizes, $item->sizes)))
            ->all();
    }

    /** Active extra fields of the chosen type that the user may see (sensitive ones need view_medical in /admin). */
    private static function extraFields(Get $get): Collection
    {
        return ExtraField::shownTo(PersonType::tryFrom((string) $get('type')) ?? PersonType::Athlete, auth()->user());
    }

    private static function extraInput(ExtraField $f): Field
    {
        $name = "extra.{$f->id}";

        $input = match ($f->type) {
            ExtraFieldType::Text => TextInput::make($name)->maxLength(255),
            ExtraFieldType::Number => TextInput::make($name)->numeric(),
            ExtraFieldType::Date => DatePicker::make($name),
            ExtraFieldType::Choice => Select::make($name)->options(array_combine($f->options ?? [], $f->options ?? [])),
            ExtraFieldType::YesNo => Select::make($name)->options(['1' => 'Yes', '0' => 'No']),
        };

        return $input->label($f->label)->helperText($f->help_text);
    }

    private static function options(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
