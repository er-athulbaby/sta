{{-- A person's name linking to their registry edit page in a new tab (the list stays open); plain text when the current user may
     not edit them (portal: only people of the current association; admin: needs Manage people). --}}
@props(['person'])
@php
    $portal = filament()->getCurrentPanel()?->getId() === 'portal';
    $resource = $portal ? \App\Filament\Portal\Resources\People\PersonResource::class : \App\Filament\Resources\People\PersonResource::class;
    $allowed = $portal ? filament()->getTenant()?->getKey() === $person->association_id : $resource::canAccess();
    $href = $allowed && $resource::canEdit($person) ? $resource::getUrl('edit', ['record' => $person]) : null;
    $name = $slot->isEmpty() ? $person->fullName() : $slot;
@endphp
@if ($href)<a href="{{ $href }}" target="_blank" rel="noopener" {{ $attributes->merge(['style' => 'color: inherit; text-decoration: underline; text-underline-offset: 2px']) }}>{{ $name }}<span class="sta-sr-only"> (opens the profile in a new tab)</span></a>@else<span {{ $attributes }}>{{ $name }}</span>@endif
