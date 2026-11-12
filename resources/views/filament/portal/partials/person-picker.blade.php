{{-- Canvas LongListEvents.dc.html token field: person chips with ×, a search input and a multi-select listbox.
     $id (input id), $placeholder, $chips (list of [person, sub, remove (wire call), removeLabel, bad]), $options (list of
     [id, person, sub]), $chosen (ids), $add / $remove (Livewire method names), $arg (first argument), $listLabel,
     $filterHtml (trusted markup, values escaped by the caller), $footer, $error. Read-only: chips without × and no input. --}}
@php
    $field = 'display: flex; flex-wrap: wrap; align-items: center; gap: 8px; min-height: 48px; box-sizing: border-box; padding: 1px 8px; border-radius: 10px; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid '.($error ? 'var(--sta-bd-red-600, #DC2626)' : 'var(--sta-bd-line-strong, #CBD5E1)');
    $avatar = 'border-radius: 50%; background: var(--sta-bg-avatar, #2B4C7E); color: var(--sta-fg-on-brand, #FFFFFF); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0';
@endphp
<div style="display: flex; flex-direction: column; gap: 8px"
    @unless ($readOnlyReason)
        x-data="{
            open: false, q: '', active: null,
            matches(text) { return this.q === '' || text.includes(this.q.trim().toLowerCase()) },
            visible() { return [...this.$refs.list.querySelectorAll('[role=option]')].filter(o => o.style.display !== 'none') },
            move(step) { const v = this.visible(); if (! v.length) return; const i = v.findIndex(o => o.id === this.active); this.active = v[(i + step + v.length) % v.length].id; document.getElementById(this.active)?.scrollIntoView({ block: 'nearest' }) },
            pick() { if (this.active) document.getElementById(this.active)?.click() },
        }"
        x-on:click.outside="open = false" x-on:keydown.escape="open = false"
    @endunless
>
    <div style="{{ $field }}" @unless ($readOnlyReason) x-bind:style="open && ! {{ $error ? 'true' : 'false' }} ? { border: '2px solid var(--sta-bd-brand, #1F3A5F)' } : {}" @endunless>
        @foreach ($chips as $c)
            <span @class(['sta-chip-person', 'is-bad' => $c['bad']]) wire:key="{{ $id }}-chip-{{ $c['person']->id }}">
                <span role="img" aria-label="Photo of {{ $c['person']->fullName() }}" style="width: 24px; height: 24px; font-size: 10px; {{ $avatar }}">{{ \App\Support\Sta::initials($c['person']->fullName()) }}</span>
                <span style="font-weight: 600">{{ $c['person']->fullName() }}</span>
                <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $c['sub'] }}</span>
                @unless ($readOnlyReason)
                    <button type="button" wire:click="{{ $remove }}({{ $arg }}, {{ $c['person']->id }})" aria-label="{{ $c['removeLabel'] }}" style="width: 44px; height: 44px; margin: -4px -4px -4px -8px; border: 0; border-radius: 6px; background: transparent; color: var(--sta-fg-muted, #475569); display: inline-flex; align-items: center; justify-content: center; padding: 0; cursor: pointer"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg></button>
                @endunless
            </span>
        @endforeach
        @unless ($readOnlyReason)
            <input id="{{ $id }}" type="text" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="{{ $id }}-list"
                x-bind:aria-expanded="open" x-bind:aria-activedescendant="active" x-model="q" placeholder="{{ $placeholder }}"
                x-on:focus="open = true" x-on:click="open = true" x-on:input="open = true; active = null"
                x-on:keydown.down.prevent="open = true; move(1)" x-on:keydown.up.prevent="open = true; move(-1)" x-on:keydown.enter.prevent="pick()"
                style="flex: 1 1 140px; min-width: 120px; min-height: 44px; border: 0; outline: none; background: transparent; font: 14px Inter, sans-serif">
        @endunless
    </div>
    @unless ($readOnlyReason)
        <div id="{{ $id }}-list" x-ref="list" role="listbox" aria-label="{{ $listLabel }}" aria-multiselectable="true" x-show="open" style="display: none; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; background: var(--sta-bg-surface, #FFFFFF); box-shadow: 0 12px 28px rgba(15,36,64,0.16); overflow: hidden">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 12px; background: var(--sta-bg-surface-alt, #F8FAFC); border-bottom: 1px solid var(--sta-bd-line, #E2E8F0); font-size: 12px; color: var(--sta-fg-muted, #475569)">
                <span><span x-show="q !== ''">Search: <strong style="color: var(--sta-fg-text, #0F172A)" x-text="q"></strong></span></span>
                <span>{!! $filterHtml !!}</span>
            </div>
            <div style="max-height: 288px; overflow-y: auto">
                @foreach ($options as $o)
                    @php $sel = in_array($o['person']->id, $chosen, true); @endphp
                    <div id="{{ $id }}-opt-{{ $o['person']->id }}" role="option" aria-selected="{{ $sel ? 'true' : 'false' }}" wire:key="{{ $id }}-opt-{{ $o['person']->id }}"
                        x-show="matches(@js(mb_strtolower($o['person']->fullName().' '.$o['person']->code.' '.$o['sub'])))"
                        wire:click="{{ $sel ? $remove : $add }}({{ $arg }}, {{ $o['person']->id }})"
                        x-bind:style="active === $el.id ? { outline: '2px solid var(--sta-bd-brand, #1F3A5F)', outlineOffset: '-2px' } : {}"
                        style="cursor: pointer; background: {{ $sel ? 'var(--sta-bg-subtle, #F1F5F9)' : 'var(--sta-bg-surface, #FFFFFF)' }}">
                        <div style="display: flex; align-items: center; gap: 10px; min-height: 48px; padding: 6px 12px">
                        <span aria-hidden="true" style="width: 18px; height: 18px; box-sizing: border-box; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; {{ $sel ? 'background: var(--sta-bg-brand, #1F3A5F); border: 2px solid var(--sta-bd-brand, #1F3A5F)' : 'background: var(--sta-bg-surface, #FFFFFF); border: 2px solid var(--sta-bd-soft, #64748B)' }}">
                            @if ($sel)<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="stroke: var(--sta-fg-on-brand, #FFFFFF)"><path d="m5 12 4 4 10-10"/></svg>@endif
                        </span>
                        <span role="img" aria-label="Photo of {{ $o['person']->fullName() }}" style="width: 32px; height: 32px; font-size: 11px; {{ $avatar }}">{{ \App\Support\Sta::initials($o['person']->fullName()) }}</span>
                        <span style="display: flex; flex-direction: column; flex: 1; min-width: 0">
                            <span style="font-weight: 600">{{ $o['person']->fullName() }}</span>
                            <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $o['person']->code }} · {{ $o['sub'] }}</span>
                        </span>
                        </div>
                    </div>
                @endforeach
            </div>
            <div style="padding: 8px 12px; border-top: 1px solid var(--sta-bd-line, #E2E8F0); font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $footer }}</div>
        </div>
    @endunless
    @if ($error)
        <div role="alert" style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--sta-fg-red-700, #B91C1C); font-weight: 600">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="flex-shrink: 0"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>
            {{ $error }}
        </div>
    @endif
</div>
