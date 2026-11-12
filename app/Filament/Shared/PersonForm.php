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
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
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

    /**
     * Canvas PlayerForm.dc.html / OfficialForm.dc.html: numbered colour-band sections in the drawn order; the numbers and the
     * jump links follow the type switch (sectionKeys()), Medical / Health only with view_medical, extra fields last.
     */
    public static function configure(Schema $schema, bool $withAssociation = false, PersonType $type = PersonType::Athlete, bool $lockType = false): Schema
    {
        $isOfficial = fn (Get $get) => $get('type') === PersonType::Official->value;
        $isAthlete = fn (Get $get) => $get('type') !== PersonType::Official->value;
        $medical = fn () => Person::canViewMedical(auth()->user());
        $countries = array_combine(array_values(config('countries')), array_values(config('countries')));
        $scanTypes = self::SCAN_TYPES;
        $scanHelp = 'PDF, JPG or PNG, up to 5 MB';
        $emptyHelp = fn (string $column, string $help) => fn (Get $get) => blank($get($column)) ? $help : null;
        $section = fn (string $key, string $class = '') => Section::make(fn (Get $get) => self::heading($key, $get))->id(fn (Get $get) => self::anchor($key, $get))
            ->extraAttributes(fn (Get $get) => ['class' => trim('sta-band sta-band-'.self::SECTIONS[$key][2].' sta-form-band '.$class)]);
        $grid = fn (array $fields, int $min = 200) => Grid::make(1)->schema($fields)->extraAttributes(['class' => 'sta-auto-grid', 'style' => "--sta-min: {$min}px"]);

        return $schema->columns(1)->components([
            $section('person')->schema([
                Flex::make([
                    self::need(self::upload('photo_path', 'Photo', 'photos', self::PHOTO_TYPES, 2048, 'photo'), 'photo_path')
                        ->helperText('JPG or PNG, up to 2 MB')->grow(false)->extraFieldWrapperAttributes(['class' => 'sta-photo-field'], merge: true),
                    $grid([
                        ...($withAssociation ? [Select::make('association_id')->relationship('association', 'name')->required()] : []),
                        Select::make('type')->options(self::options(PersonType::cases()))->required()->live()->default($type->value)->disabled($lockType)->dehydrated()->selectablePlaceholder(false),
                        self::need(TextInput::make('full_name')->label('Full name (as in passport)')->maxLength(200), 'full_name')->columnSpanFull(),
                        TextInput::make('first_name')->required()->maxLength(100),
                        TextInput::make('second_name')->maxLength(100),
                        TextInput::make('third_name')->maxLength(100),
                        TextInput::make('last_name')->required()->maxLength(100),
                        DatePicker::make('dob')->label('Date of birth')->required()->maxDate(now())->live(),
                        Placeholder::make('age')->label('Age')->extraAttributes(['class' => 'sta-output'])
                            ->content(fn (Get $get) => self::age($get('dob')) !== null ? self::age($get('dob')).' years · from date of birth' : 'From date of birth'),
                        Select::make('gender')->options(self::options(PersonGender::cases()))->placeholder('Select')->required(),
                        Select::make('nationality')->options($countries)->placeholder('Select country')->required(),
                        TextInput::make('height_cm')->label('Height (cm)')->numeric()->minValue(50)->maxValue(250),
                        TextInput::make('weight_kg')->label('Weight (kg)')->numeric()->minValue(20)->maxValue(250),
                        Toggle::make('is_active')->label('Active')->default(true)->view('filament.people.switch-field'),
                    ]),
                ])->from('md'),
            ]),
            $section('role')->visible($isOfficial)->schema([
                $grid([
                    Select::make('function')->options(array_combine(self::FUNCTIONS, self::FUNCTIONS))->placeholder('Select')->required($isOfficial),
                    TextInput::make('languages')->label('Languages spoken')->maxLength(255),
                ], 260),
            ]),
            $section('passport')->schema([
                Flex::make([
                    self::need(self::upload('passport_scan_path', 'Passport scan', 'scans', $scanTypes, 5120), 'passport_scan_path')
                        // A new or removed scan makes the last "Read details from passport" result stale.
                        ->afterStateUpdated(function ($livewire) {
                            if (property_exists($livewire, 'passportRead')) {
                                $livewire->passportRead = null;
                            }
                        })
                        ->extraFieldWrapperAttributes(['class' => 'sta-flex-320'], merge: true)
                        ->belowContent([
                            $scanHelp,
                            ReadPassportAction::make(),
                            Text::make('Reads names, passport number, nationality, date of birth, gender and expiry date from the machine-readable lines at the bottom of the photo page. Check every value before saving. Enter the issue date by hand.')
                                ->extraAttributes(['class' => 'sta-help']),
                            View::make('filament.people.passport-read'),
                        ]),
                    $grid([
                        TextInput::make('passport_no')->label('Passport number')->required()->maxLength(30)
                            ->rule(fn (?Model $record) => self::uniqueDocument('passport_no_hash', 'passport', $record)),
                        self::need(Select::make('passport_issuing_country')->label('Issuing country')->options($countries)->placeholder('Select country'), 'passport_issuing_country'),
                        self::need(DatePicker::make('passport_issue_date')->label('Issue date')->maxDate(now()), 'passport_issue_date'),
                        DatePicker::make('passport_expiry')->label('Expiry date')->required(),
                    ])->grow(),
                ])->from('md'),
            ]),
            $section('cpr')->schema([
                $grid([
                    self::need(TextInput::make('cpr_no')->label('CPR number')->maxLength(30)
                        ->rule(fn (?Model $record) => self::uniqueDocument('cpr_no_hash', 'CPR', $record)), 'cpr_no'),
                    DatePicker::make('cpr_expiry')->label('CPR expiry')->requiredWith('cpr_no'),
                ], 240),
                $grid([
                    // The canvas shows the file-type hint only under an empty upload.
                    self::upload('cpr_front_path', 'CPR front', 'scans', $scanTypes, 5120)->helperText($emptyHelp('cpr_front_path', $scanHelp)),
                    self::upload('cpr_back_path', 'CPR back', 'scans', $scanTypes, 5120)->helperText($emptyHelp('cpr_back_path', $scanHelp)),
                    self::upload('cpr_reader_path', 'CPR reader copy', 'scans', $scanTypes, 5120, uploadLabel: 'Upload reader printout')
                        ->helperText($emptyHelp('cpr_reader_path', 'Smart-card reader printout · '.$scanHelp)),
                ], 260),
            ]),
            $section('contact')->schema([
                $grid([
                    // The label sits on the number input; the code select carries "… country code" for screen readers.
                    PhoneField::make('phone', fn (Get $get) => $isOfficial($get) ? 'Mobile' : 'Player mobile', fn (TextInput $i) => self::need($i, 'phone'), 'Mobile country code'),
                    Toggle::make('whatsapp_same')->label('WhatsApp is the same as mobile')->live()->view('filament.people.switch-field'),
                    PhoneField::make('whatsapp', 'WhatsApp', fn (TextInput $i) => self::need($i, 'whatsapp'), 'WhatsApp country code')->hidden(fn (Get $get) => (bool) $get('whatsapp_same')),
                    self::need(TextInput::make('email')->label(fn (Get $get) => $isOfficial($get) ? 'Email' : 'Player email')->email()->maxLength(255), 'email'),
                    TextInput::make('address')->label('Player address')->maxLength(255)->visible($isAthlete)->columnSpanFull(),
                    self::need(TextInput::make('emergency_name')->label('Emergency contact name')->maxLength(200), 'emergency_name'),
                    self::need(TextInput::make('emergency_relationship')->label('Relationship')->maxLength(100), 'emergency_relationship'),
                    PhoneField::make('emergency_phone', 'Emergency phone', fn (TextInput $i) => self::need($i, 'emergency_phone'), 'Emergency phone country code'),
                ], 260),
            ]),
            $section('guardian')->visible($isAthlete)->description(fn (Get $get) => self::guardianNote($get))->schema([
                $grid([
                    TextInput::make('guardian_name')->maxLength(200),
                    Select::make('guardian_relationship')->label('Relationship')->options(array_combine($g = ['Father', 'Mother', 'Legal guardian'], $g))->placeholder('Select'),
                    PhoneField::make('guardian_phone', 'Guardian phone', codeLabel: 'Guardian phone country code'),
                    TextInput::make('guardian_email')->email()->maxLength(255),
                ], 240),
            ]),
            $section('visas', 'sta-has-add')->visible($isOfficial)->description('Add one row per country')->schema([
                Repeater::make('visas')->hiddenLabel()->relationship()->defaultItems(0)->addActionLabel('Add visa')->reorderable(false)->deleteAction(self::removeRow('visa'))
                    ->itemLabel(fn () => null)->extraAttributes(['class' => 'sta-repeater'])->schema([
                        Grid::make(1)->extraAttributes(['class' => 'sta-auto-grid', 'style' => '--sta-min: 170px'])->schema([
                            Select::make('country')->options($countries)->placeholder('Select country')->required(),
                            Select::make('status')->options(array_combine(PersonVisa::STATUSES, PersonVisa::STATUSES))->placeholder('Select')->required(),
                            TextInput::make('visa_no')->label('Visa number')->maxLength(50),
                            DatePicker::make('valid_until'),
                        ]),
                        self::trustFreshUploads(self::upload('copy_path', 'Visa copy', 'visas', $scanTypes, 5120))->helperText($emptyHelp('copy_path', $scanHelp)),
                    ]),
                // Shown by CSS only while the repeater has no rows (the repeater re-renders alone when a row is added).
                Text::make('No visas recorded.')->extraAttributes(['class' => 'sta-help sta-empty-note']),
            ]),
            $section('clothing')->schema([
                Grid::make(1)->extraAttributes(['class' => 'sta-auto-grid', 'style' => '--sta-min: 160px'])->schema(fn (Get $get) => [
                    Select::make('fit')->options(["Men's" => 'Male', "Women's" => 'Female', 'Youth' => 'Youth'])->placeholder('Select'),
                    TextInput::make('chest_cm')->label('Chest (cm)')->numeric()->minValue(40)->maxValue(200),
                    TextInput::make('waist_cm')->label('Waist (cm)')->numeric()->minValue(40)->maxValue(200),
                    ...self::sizeFields(PersonType::tryFrom((string) $get('type')) ?? PersonType::Athlete),
                ]),
            ]),
            // One section for both types (field names must be unique in a schema); title and type-only fields switch on type.
            // The whole section is left out of the schema (->hidden, not just invisible) for STA users without view_medical.
            $section('medical')->hidden(fn () => ! $medical())
                ->afterHeader([Text::make('Visible to your association and STA staff with medical permission')->extraAttributes(['class' => 'sta-help'])])
                ->schema([
                    $grid([
                        Select::make('blood_group')->options(array_combine($b = ['A+', 'A−', 'B+', 'B−', 'AB+', 'AB−', 'O+', 'O−', 'Unknown'], $b))->placeholder('Select'),
                        Select::make('meal_requirement')->placeholder('Select')->visible($isOfficial)
                            ->options(array_combine($m = ['Halal', 'Vegetarian', 'Vegan', 'Gluten-free', 'No restriction'], $m)),
                        DatePicker::make('medical_clearance_expiry')->visible($isAthlete),
                        TextInput::make('insurance_policy_no')->label('Insurance policy number')->maxLength(100)->visible($isAthlete),
                        self::upload('wada_certificate_path', 'WADA certificate', 'scans', $scanTypes, 5120)->visible($isAthlete),
                    ], 220),
                    $grid([
                        Textarea::make('allergies')->rows(3),
                        Textarea::make('chronic_conditions')->label(fn (Get $get) => $isOfficial($get) ? 'Medical conditions' : 'Chronic conditions')->rows(3),
                        Textarea::make('medication')->label(fn (Get $get) => $isOfficial($get) ? 'Medication' : 'Current medication')->rows(3),
                    ], 240),
                    // Filament loads relationships even for hidden components; load nothing without view_medical.
                    Repeater::make('injuries')->relationship(modifyQueryUsing: fn (Builder $query) => $medical() ? $query : $query->whereRaw('1 = 0'))->visible($isAthlete)
                        ->defaultItems(0)->addActionLabel('Add injury')->reorderable(false)->deleteAction(self::removeRow('injury'))->itemLabel(fn () => null)
                        ->extraAttributes(['class' => 'sta-repeater'])->extraFieldWrapperAttributes(['class' => 'sta-injuries'])->schema([
                            Grid::make(1)->extraAttributes(['class' => 'sta-auto-grid', 'style' => '--sta-min: 160px'])->schema([
                                DatePicker::make('injury_date')->label('Date')->required(),
                                // Same rules as before (a required textarea); one line high like the canvas input.
                                Textarea::make('description')->required()->rows(1)->placeholder('e.g. Right shoulder strain'),
                                Select::make('status')->options(array_combine(PersonInjury::STATUSES, PersonInjury::STATUSES))->placeholder('Select')->required(),
                            ]),
                        ]),
                    Text::make('No injuries recorded.')->extraAttributes(['class' => 'sta-help sta-empty-note'])->visible($isAthlete),
                ]),
            $section('consents', 'sta-consents')->visible($isAthlete)->schema([
                self::consent('consent_data', 'Consent to store personal data', 'Keep this profile, documents and scans in the association registry.'),
                self::consent('consent_media', 'Consent to use photos in media', 'Photos may appear in official game media and press material.'),
                self::consent('consent_medical', 'Consent for emergency medical treatment', 'Event medical staff may treat the athlete in an emergency.'),
            ]),
            $section('declarations', 'sta-consents')->visible($isOfficial)->schema([
                self::consent('decl_conduct', 'Accepts the delegation code of conduct', 'Applies for the whole delegation period, travel included.'),
                self::consent('decl_antidoping', 'Will follow anti-doping and integrity rules', 'WADA code and the games’ integrity and betting rules.'),
                self::consent('decl_data_sharing', 'Consents to data being shared with organisers, airline and hotel', 'Only what each needs for accreditation, flights and rooms.'),
            ]),
            $section('extra')->hidden(fn (Get $get) => self::extraFields($get)->isEmpty())->schema([
                Grid::make(1)->extraAttributes(['class' => 'sta-auto-grid', 'style' => '--sta-min: 240px'])
                    ->schema(fn (Get $get) => self::extraFields($get)->map(fn (ExtraField $f) => self::extraInput($f))->all()),
            ]),
        ]);
    }

    /** anchor => [jump-link label, heading, band, dot colour, icon paths] (canvas PlayerForm / OfficialForm). */
    public const SECTIONS = [
        'person' => ['Person', 'Person', 'blue', '#3B82F6', '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
        'role' => ['Official details', 'Team official details', 'cyan', '#0E7490', '<rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4h6v3H9z"/><path d="M9 12h6"/><path d="M9 16h4"/>'],
        'passport' => ['Passport', 'Passport', 'indigo', '#4338CA', '<rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M9 17h6"/>'],
        'cpr' => ['CPR', 'CPR', 'violet', '#8B5CF6', '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="12" r="2"/><path d="M14 10h4"/><path d="M14 14h4"/>'],
        'contact' => ['Contact', 'Contact', 'teal', '#115E59', '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>'],
        'guardian' => ['Guardian', 'Guardian', 'amber', '#D97706', '<path d="M12 3l7 3v5c0 5-3 8.5-7 10-4-1.5-7-5-7-10V6z"/>'],
        'visas' => ['Visas', 'Visas', 'green', '#16A34A', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>'],
        'clothing' => ['Clothing', 'Clothing', 'slate', '#475569', '<path d="M8 3 3 6l2 5 3-1v11h8V10l3 1 2-5-5-3a4 4 0 0 1-8 0z"/>'],
        'medical' => ['Medical', 'Medical', 'rose', '#E11D48', '<path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.5-7 10-7 10z"/>'],
        'health' => ['Health', 'Health', 'rose', '#E11D48', '<path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.5-7 10-7 10z"/>'],
        'consents' => ['Consents', 'Consents', 'fuchsia', '#86198F', '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8 12 3 3 5-6"/>'],
        'declarations' => ['Declarations', 'Declarations', 'fuchsia', '#86198F', '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8 12 3 3 5-6"/>'],
        'extra' => ['Additional information', 'Additional information', 'slate', '#475569', '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>'],
    ];

    /** Which section each completeness field lives in (jump-link "N missing" badges). */
    public const FIELD_SECTIONS = ['full_name' => 'person', 'photo_path' => 'person', 'passport_issuing_country' => 'passport',
        'passport_issue_date' => 'passport', 'passport_scan_path' => 'passport', 'cpr_no' => 'cpr', 'phone' => 'contact',
        'whatsapp' => 'contact', 'email' => 'contact', 'emergency_name' => 'contact', 'emergency_relationship' => 'contact', 'emergency_phone' => 'contact'];

    /** @return list<string> the anchors shown for a type, in canvas order (Medical / Health only with view_medical; extra fields last) */
    public static function sectionKeys(PersonType $type, bool $medical, bool $extra): array
    {
        $keys = $type === PersonType::Official
            ? ['person', 'role', 'passport', 'cpr', 'contact', 'visas', 'clothing', 'health', 'declarations', 'extra']
            : ['person', 'passport', 'cpr', 'contact', 'guardian', 'clothing', 'medical', 'consents', 'extra'];

        return array_values(array_filter($keys, fn (string $k) => ($medical || ! in_array($k, ['medical', 'health'], true)) && ($extra || $k !== 'extra')));
    }

    /** @return list<string> sectionKeys() for the form's current state */
    public static function sectionKeysFor(?string $type): array
    {
        $type = PersonType::tryFrom((string) $type) ?? PersonType::Athlete;

        return self::sectionKeys($type, Person::canViewMedical(auth()->user()), ExtraField::shownTo($type, auth()->user())->isNotEmpty());
    }

    /** The Medical section is "Health" (#health) for team officials. */
    private static function anchor(string $key, Get $get): string
    {
        return $key === 'medical' && $get('type') === PersonType::Official->value ? 'health' : $key;
    }

    private static function heading(string $key, Get $get): HtmlString
    {
        $key = self::anchor($key, $get);
        $number = array_search($key, self::sectionKeysFor($get('type')), true);
        [, $title, , , $icon] = self::SECTIONS[$key];
        $lock = in_array($key, ['medical', 'health'], true)
            ? '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>' : '';

        return new HtmlString('<span class="sta-band-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            .$icon.'</svg></span>'.$lock.e(($number === false ? '' : ($number + 1).' · ').$title));
    }

    /** "needed for Long List" next to the label (canvas); "Missing · needed for Long List" and an orange border on a saved person. */
    private static function need(Field $field, string $column): Field
    {
        $applies = fn (Get $get) => array_key_exists($column, Person::REQUIRED_FOR_COMPLETE[$get('type') === PersonType::Official->value ? 'official' : 'athlete']);
        $missing = fn (Get $get, ?Model $record) => $record?->exists && blank($get($column));
        $hint = fn (Get $get, ?Model $record) => match (true) {
            $applies($get) => new HtmlString($missing($get, $record) ? '<span class="sta-need is-missing">Missing · needed for Long List</span>' : '<span class="sta-need">needed for Long List</span>'),
            // Team officials from outside Bahrain may have no CPR.
            $column === 'cpr_no' => new HtmlString('<span class="sta-need">optional</span>'),
            default => null,
        };
        $class = fn (Get $get, ?Model $record) => $applies($get) && $missing($get, $record) ? ['class' => 'is-missing'] : [];

        return $field->hint($hint)->extraFieldWrapperAttributes($class, merge: true);
    }

    /** Canvas row bin: the repeater's own delete action as a 44px button at the end of the row, labelled "Remove {noun} {n}". */
    private static function removeRow(string $noun): Closure
    {
        return fn (Action $action) => $action
            ->label(fn (array $arguments, Repeater $component) => 'Remove '.$noun.' '.((int) array_search($arguments['item'] ?? null, array_keys($component->getRawState() ?? []), true) + 1))
            ->icon(new HtmlString('<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/></svg>'))
            ->extraAttributes(['class' => 'sta-row-remove']);
    }

    private static function consent(string $name, string $label, string $description): Toggle
    {
        return Toggle::make($name)->label($label)->view('filament.people.consent-field', ['description' => $description]);
    }

    private static function age(mixed $dob): ?int
    {
        try {
            return filled($dob) ? (int) Carbon::parse($dob)->diffInYears(now()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function guardianNote(Get $get): string
    {
        $age = self::age($get('dob'));
        $name = trim((string) $get('first_name'));

        return $age !== null && $age >= 18 && $name !== ''
            ? "For athletes under 18. {$name} is {$age}, so this section is optional."
            : 'For athletes under 18.';
    }

    public const PHOTO_TYPES = ['image/jpeg', 'image/png'];

    public const SCAN_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    /**
     * The registry rules for the fields of the Long List side panel's short form (canvas LongList.dc.html), keyed
     * "{$prefix}field" ($record: the person being edited, ignored by the document uniqueness checks): the same requirements, lengths, option lists, document uniqueness and upload limits as configure().
     *
     * @return array<string, array<int, mixed>>
     */
    public static function shortFormRules(PersonType $type, string $prefix = '', string $uploadPrefix = '', ?Person $record = null): array
    {
        $official = $type === PersonType::Official;
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(PersonGender::class)],
            'dob' => ['required', 'date', 'before_or_equal:today'],
            'nationality' => ['required', Rule::in(array_values(config('countries')))],
            'passport_no' => ['required', 'string', 'max:30', self::uniqueDocument('passport_no_hash', 'passport', $record)],
            'passport_expiry' => ['required', 'date'],
            'cpr_no' => ['nullable', 'string', 'max:30', self::uniqueDocument('cpr_no_hash', 'CPR', $record)],
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

    /** $variant: "photo" (canvas photo column) or "row" (file row with View / Replace, dashed upload button when empty). */
    private static function upload(string $name, string $label, string $dir, array $types, int $maxKb, string $variant = 'row', ?string $uploadLabel = null): FileUpload
    {
        return AttachmentActions::view(FileUpload::make($name)->label($label)
            ->view('filament.people.upload-field', ['variant' => $variant, 'uploadLabel' => $uploadLabel ?? 'Upload '.(ctype_upper(substr($label, 1, 1)) ? $label : lcfirst($label))])
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
            ->map(fn (ClothingItem $item) => Select::make("sizes.{$item->id}")->label($item->name.' size')
                ->options(array_combine($item->sizes, $item->sizes))->placeholder('Select'))
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
