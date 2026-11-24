{{-- Date field that reads "24 Nov 2026" (Sta::DATE) in every browser locale; a plain <input type="date"> shows mm/dd/yyyy in US
     browsers. The hidden native input keeps the Y-m-d value and every attribute passed in (wire:model, value, wire:change, min,
     max); the visible box takes typing (24 Nov 2026, 24/11/2026 or 2026-11-24) and the calendar button opens the browser's picker.
     After each Livewire update the box re-reads the native value, so server-side changes show too. --}}
<span x-data="{
        label: '',
        months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        show(v) { const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || ''); return m ? m[3] + ' ' + this.months[m[2] - 1] + ' ' + m[1] : '' },
        parse(t) {
            t = t.trim();
            if (t === '') return '';
            let r = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(t), y, m, d;
            if (r) { [y, m, d] = [r[1], r[2], r[3]] }
            else if (r = /^(\d{1,2})[\s\/.-]+([a-z]+|\d{1,2})[\s\/.,-]+(\d{4})$/i.exec(t)) {
                [d, y] = [r[1], r[3]];
                m = /\d/.test(r[2]) ? r[2] : this.months.findIndex(x => r[2].length >= 3 && x.toLowerCase() === r[2].slice(0, 3).toLowerCase()) + 1;
            } else return null;
            const dt = new Date(Date.UTC(y, m - 1, d));
            return m > 0 && dt.getUTCDate() == d && dt.getUTCMonth() == m - 1 ? dt.toISOString().slice(0, 10) : null;
        },
        sync() { if (document.activeElement !== this.$refs.box) this.$refs.box.value = this.label = this.show(this.$refs.native.value) },
        commit() {
            const v = this.parse(this.$refs.box.value), n = this.$refs.native;
            if (v !== null && v !== n.value) { n.value = v; ['input', 'change', 'blur'].forEach(e => n.dispatchEvent(new Event(e, { bubbles: e !== 'blur' }))) }
            this.label = this.show(n.value); this.$refs.box.value = this.label;
        },
        pick() { try { this.$refs.native.showPicker() } catch (e) { this.$refs.native.focus() } },
        init() {
            if (! window.staDateSync) { window.staDateSync = true; Livewire.hook('morphed', () => setTimeout(() => window.dispatchEvent(new Event('sta-date-sync')))) }
            setTimeout(() => this.sync());
        },
    }" x-on:sta-date-sync.window="sync()" style="position: relative; display: inline-flex; align-items: center; flex: 1 1 auto; min-width: 0">
    <input x-ref="box" type="text" x-bind:value="label" x-on:change="commit()" x-on:keydown.alt.down.prevent="pick()" placeholder="dd Mmm yyyy" autocomplete="off"
        @if ($attributes->has('aria-label')) aria-label="{{ $attributes->get('aria-label') }}" @endif
        style="{{ $attributes->get('style') }}; width: 100%; box-sizing: border-box; padding-right: 40px">
    <button type="button" x-on:click="pick()" aria-label="Open calendar" title="Open calendar"
        style="position: absolute; right: 4px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 8px; background: transparent; color: var(--sta-fg-muted, #475569); cursor: pointer">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
    </button>
    <input x-ref="native" type="date" {{ $attributes->except(['style', 'aria-label']) }} x-on:input="sync()" x-on:change="sync()" tabindex="-1" aria-hidden="true"
        style="position: absolute; left: 0; bottom: 0; width: 100%; height: 1px; padding: 0; border: 0; opacity: 0; pointer-events: none">
</span>
