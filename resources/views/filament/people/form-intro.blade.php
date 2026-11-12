{{-- Canvas required-fields note and the "Form sections" jump links (numbers follow the type; "N missing" on a saved person). --}}
@php
    $type = $this->personType();
    $person = $this->personRecord();
    $keys = \App\Filament\Shared\PersonForm::sectionKeysFor($type->value);
    $counts = [];
    if ($person && $person->type === $type) {
        $columns = collect(\App\Models\Person::REQUIRED_FOR_COMPLETE[$type->value])->filter(fn ($label, $column) => blank($person->{$column}));
        foreach ($columns->keys() as $column) {
            $section = \App\Filament\Shared\PersonForm::FIELD_SECTIONS[$column] ?? 'person';
            $counts[$section] = ($counts[$section] ?? 0) + 1;
        }
        if (($extra = count($person->missingFields()) - $columns->count()) > 0) {
            $counts['extra'] = $extra;
        }
    }
@endphp
<p style="margin: 0; font-size: 13px; color: var(--sta-fg-muted, #475569); line-height: 1.5"><span aria-hidden="true" style="color: var(--sta-fg-orange-800, #9A3412); font-weight: 700">*</span> Required to save. Fields marked “needed for Long List” can stay empty for now, but the {{ $type === \App\Enums\PersonType::Official ? 'official' : 'athlete' }} stays Incomplete and can’t be entered in a Long List until they’re filled. Uploaded files open through a secure link valid for 5 minutes.</p>

<nav aria-label="Form sections" style="display: flex; flex-wrap: wrap; gap: 8px">
    @foreach ($keys as $i => $key)
        @php [$label, , , $dot] = \App\Filament\Shared\PersonForm::SECTIONS[$key]; @endphp
        <a href="#{{ $key }}" style="display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 0 14px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); text-decoration: none; font-weight: 600; font-size: 13px; box-sizing: border-box"><span aria-hidden="true" style="width: 10px; height: 10px; border-radius: 50%; background: {{ $dot }}; flex-shrink: 0"></span>{{ $i + 1 }} {{ $label }}@if ($counts[$key] ?? 0) <span style="display: inline-flex; align-items: center; height: 20px; padding: 0 7px; border-radius: 999px; background: var(--sta-bg-orange-100, #FFEDD5); color: var(--sta-fg-orange-800, #9A3412); font-size: 11px">{{ $counts[$key] }} missing</span>@endif</a>
    @endforeach
</nav>
