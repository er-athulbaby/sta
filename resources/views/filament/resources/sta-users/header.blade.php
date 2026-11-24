{{-- "Users & groups": h1, the Groups / Users tabs and the New button of the open tab. $tab: groups | users. --}}
@php
    $groups = \App\Filament\Resources\StaGroups\StaGroupResource::class;
    $users = \App\Filament\Resources\StaUsers\StaUserResource::class;
    $tabs = ['groups' => ['Groups', $groups], 'users' => ['Users', $users]];
    $resource = $tabs[$tab][1];
@endphp
<header style="display: flex; flex-direction: column; gap: 14px">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px">
        <div style="display: flex; flex-direction: column; gap: 6px">
            <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700">Users &amp; groups</h1>
            <span style="color: var(--sta-fg-muted, #475569)">Each STA user belongs to one group; the group's grid says what they may open and do. Super Admins can do everything.</span>
        </div>
        @if ($resource::canCreate())
            <a href="{{ $resource::getUrl('create') }}" style="min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box">{{ $tab === 'groups' ? 'New group' : 'New user' }}</a>
        @endif
    </div>
    <nav aria-label="Users and groups" style="display: flex; flex-wrap: wrap; gap: 4px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
        @foreach ($tabs as $key => [$label, $class])
            <a href="{{ $class::getUrl() }}" @if ($key === $tab) aria-current="page" @endif
               style="display: inline-flex; align-items: center; min-height: 44px; padding: 0 16px; border-bottom: 2px solid {{ $key === $tab ? 'var(--sta-bd-brand, #1F3A5F)' : 'transparent' }}; color: {{ $key === $tab ? 'var(--sta-fg-text, #0F172A)' : 'var(--sta-fg-muted, #475569)' }}; font-weight: 600; text-decoration: none">{{ $label }}</a>
        @endforeach
    </nav>
</header>
