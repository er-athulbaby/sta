<x-filament-panels::page>
@php
    $PH = [1 => ['var(--sta-bg-sky-100, #E0F2FE)', 'var(--sta-bg-sky-50, #F0F9FF)', 'var(--sta-bd-sky-200, #BAE6FD)', 'var(--sta-fg-sky-800, #075985)', 'var(--sta-bg-sky-700, #0369A1)'], 2 => ['var(--sta-bg-teal-100, #CCFBF1)', 'var(--sta-bg-teal-50, #F0FDFA)', 'var(--sta-bd-teal-200, #99F6E4)', 'var(--sta-fg-teal-800, #115E59)', 'var(--sta-bg-teal-700, #0F766E)'],
           3 => ['var(--sta-bg-amber-100, #FEF3C7)', 'var(--sta-bg-amber-50, #FFFBEB)', 'var(--sta-bd-amber-200, #FDE68A)', 'var(--sta-fg-amber-800, #92400E)', 'var(--sta-bg-amber-700, #B45309)'], 4 => ['var(--sta-bg-violet-100, #EDE9FE)', 'var(--sta-bg-violet-50, #F5F3FF)', 'var(--sta-bd-violet-200, #DDD6FE)', 'var(--sta-fg-violet-800, #5B21B6)', 'var(--sta-bg-violet-700, #6D28D9)']];
    $tz = 'Asia/Bahrain';
    $invited = $record->associations()->count();
    $dateStyle = 'min-height: 44px; padding: 0 12px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; font: 14px Inter, sans-serif';
@endphp

<div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 14px 18px; border-radius: 12px; background: var(--sta-bg-blue-50, #EFF6FF); color: var(--sta-fg-blue-900, #1E3A8A); border: 1px solid var(--sta-bd-blue-200, #BFDBFE)">
  <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>
  <span>Turn on the phases this game needs. Only one phase can be open at a time. Opening a phase emails all {{ $invited }} invited associations; associations can edit only while it is open and before its deadline.</span>
</div>

<section aria-label="Phases" style="display: flex; flex-direction: column; gap: 12px">
  @foreach ($this->phases() as $p)
    @php
        $c = $PH[$p->phase];
        $on = $p->enabled;
        $isOpen = $on && $p->status === \App\Enums\PhaseStatus::Open;
        [$statusText, $statusKey] = ! $on ? ['Not used', 'skipped'] : match ($p->status) {
            \App\Enums\PhaseStatus::Open => ['Open', 'open'], \App\Enums\PhaseStatus::Closed => ['Closed', 'closed'], default => ['Not opened', 'none'] };
        $b = $this->button($p);
        $btnBase = 'min-height: 44px; padding: 0 18px; border-radius: 10px; font: 600 14px Inter, sans-serif; cursor: pointer; ';
        $btnStyle = $btnBase.match (true) {
            $b['disabled'] => 'border: 1px solid var(--sta-bd-line, #E2E8F0); background: var(--sta-bg-surface-alt, #F8FAFC); color: var(--sta-fg-faint, #94A3B8); cursor: not-allowed',
            $b['kind'] === 'primary' => 'border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF)',
            $b['kind'] === 'danger' => 'border: 1px solid var(--sta-bd-red-300, #FCA5A5); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-red-800, #991B1B)',
            default => 'border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A)' };
    @endphp
    <article wire:key="phase-{{ $p->phase }}" style="background: {{ $on ? $c[1] : 'var(--sta-bg-surface-off, #FAFBFC)' }}; border-radius: 14px; padding: 18px 20px; display: flex; flex-direction: column; gap: 14px; border: 1px solid {{ $isOpen ? $c[4] : $c[2] }}{{ $isOpen ? '; box-shadow: 0 0 0 3px '.$c[0] : '' }}">
      <div style="display: flex; flex-wrap: wrap; align-items: flex-start; gap: 16px">
        <div style="width: 44px; height: 44px; border-radius: 12px; background: {{ $isOpen ? $c[4] : $c[0] }}; color: {{ $isOpen ? 'var(--sta-fg-on-brand, #FFFFFF)' : $c[3] }}; display: flex; align-items: center; justify-content: center; font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 18px; flex-shrink: 0">{{ $p->phase }}</div>
        <div style="flex: 1 1 260px; display: flex; flex-direction: column; gap: 4px">
          <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px">
            <x-sta.phase-chip :phase="$p->phase" />
            <h2 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600">{{ \App\Models\GamePhase::NAMES[$p->phase] }}</h2>
            <x-sta.pill :status="$statusKey">{{ $statusText }}</x-sta.pill>
          </div>
          <span style="color: var(--sta-fg-muted, #475569); line-height: 1.5">{{ $this::WHAT[$p->phase] }}</span>
          <span style="font-size: 13px; color: var(--sta-fg-strong, #334155)">{{ $this->progress($p) }}</span>
        </div>
        <label style="display: flex; align-items: center; gap: 10px; min-height: 44px; font-weight: 600">
          <button type="button" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="Use {{ \App\Models\GamePhase::NAMES[$p->phase] }} in this game" wire:click="toggle({{ $p->phase }})"
                  style="position: relative; width: 52px; height: 30px; border-radius: 999px; border: 0; padding: 0; cursor: pointer; background: {{ $on ? 'var(--sta-bg-brand, #1F3A5F)' : 'var(--sta-bg-line-strong, #CBD5E1)' }}">
            <span style="position: absolute; top: 3px; left: {{ $on ? '25px' : '3px' }}; width: 24px; height: 24px; border-radius: 50%; background: var(--sta-bg-surface, #FFFFFF); box-shadow: 0 1px 2px rgba(15,23,42,0.25)"></span>
          </button>
          {{ $on ? 'Used' : 'Skipped' }}
        </label>
      </div>
      @if ($on)
        <div style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; padding-top: 14px; border-top: 1px solid var(--sta-bd-row, #EEF2F6)">
          <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">Opens
            <input type="date" value="{{ $p->opens_at?->timezone($tz)->format('Y-m-d') }}" wire:change="setDate({{ $p->phase }}, 'opens_at', $event.target.value)" style="{{ $dateStyle }}">
          </label>
          <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">Deadline
            <input type="date" value="{{ $p->deadline?->timezone($tz)->format('Y-m-d') }}" wire:change="setDate({{ $p->phase }}, 'deadline', $event.target.value)" style="{{ $dateStyle }}">
          </label>
          <div style="flex: 1 1 200px"></div>
          <span style="font-size: 13px; color: var(--sta-fg-muted, #475569); align-self: center">{{ $b['hint'] }}</span>
          <button type="button" @disabled($b['disabled']) wire:click="mountAction('{{ $p->status === \App\Enums\PhaseStatus::Open ? 'closePhase' : 'openPhase' }}', { phase: {{ $p->phase }} })" style="{{ $btnStyle }}">{{ $b['label'] }}</button>
        </div>
      @endif
    </article>
  @endforeach
</section>

<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">
  <section aria-labelledby="rem-title" style="flex: 1 1 340px; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; padding: 18px 20px; display: flex; flex-direction: column; gap: 14px">
    <h2 id="rem-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600">Automatic reminders</h2>
    <span style="color: var(--sta-fg-muted, #475569); line-height: 1.5">Sent to associations that have not submitted the open phase.</span>
    <div role="group" aria-label="Days before deadline" style="display: flex; flex-wrap: wrap; gap: 8px">
      @foreach ($this->reminders() as $r)
        <button type="button" wire:key="rem-{{ $r['days'] }}" wire:click="toggleReminder({{ $r['days'] }})" aria-pressed="{{ $r['on'] ? 'true' : 'false' }}"
                style="min-height: 40px; padding: 0 14px; border-radius: 999px; font: 600 13px Inter, sans-serif; cursor: pointer; {{ $r['on'] ? 'border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF)' : 'border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-strong, #334155)' }}">{{ $r['label'] }}</button>
      @endforeach
    </div>
    <span style="font-size: 13px; color: var(--sta-fg-strong, #334155)">Plus a daily reminder while overdue. "Remind pending" on the dashboard sends one any time.</span>
  </section>

  <section aria-labelledby="ext-title" style="flex: 999 1 520px; min-width: 0; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 18px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
      <div style="display: flex; flex-direction: column; gap: 2px">
        <h2 id="ext-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600">Extensions for one association</h2>
        <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">A later deadline, or re-open a submitted phase so they can correct it</span>
      </div>
      <button type="button" wire:click="mountAction('addExtension')" style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 14px Inter, sans-serif; cursor: pointer">Add extension</button>
    </div>
    <div style="overflow-x: auto">
      <table style="width: 100%; min-width: 560px; border-collapse: collapse">
        <thead>
          <tr style="text-align: left; font-size: 12px; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em">
            <th scope="col" style="padding: 12px 20px; font-weight: 600">Association</th>
            <th scope="col" style="padding: 12px; font-weight: 600">Phase</th>
            <th scope="col" style="padding: 12px; font-weight: 600">Deadline</th>
            <th scope="col" style="padding: 12px 20px; font-weight: 600">Note</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($this->extensions() as $s)
            @php $phaseDeadline = $record->phase($s->phase)->deadline; @endphp
            <tr wire:key="ext-{{ $s->id }}" style="border-top: 1px solid var(--sta-bd-row, #EEF2F6)">
              <td style="padding: 14px 20px; font-weight: 600">{{ $s->association->name }}</td>
              <td style="padding: 14px 12px"><x-sta.phase-chip :phase="$s->phase" :label="$s->phase.' · '.\App\Models\GamePhase::NAMES[$s->phase]" /></td>
              <td style="padding: 14px 12px">
                @if ($s->deadline_change === 'reopened')Re-opened → @elseif ($phaseDeadline)<span style="color: var(--sta-fg-muted, #475569); text-decoration: line-through">{{ $phaseDeadline->timezone($tz)->format('d M') }}</span> → @endif<strong>{{ $s->deadline_override->timezone($tz)->format('d M') }}</strong>
              </td>
              <td style="padding: 14px 20px; color: var(--sta-fg-strong, #334155)">{{ $s->deadline_note }}</td>
            </tr>
          @empty
            <tr><td colspan="4" style="padding: 14px 20px; color: var(--sta-fg-muted, #475569)">No extensions yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
</x-filament-panels::page>
