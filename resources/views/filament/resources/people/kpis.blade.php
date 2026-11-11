{{-- Canvas AdminPeople.dc.html summary tiles (SQL counts from ListPeople::kpis()). --}}
<section aria-label="Summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px">
    @foreach ([['Athletes', $kpis['athletes'], null], ['Team officials', $kpis['officials'], null],
        ['Passport expires within 6 months', $kpis['expiring'], '#9A3412'], ['Missing passport scan', $kpis['missingScan'], '#9A3412']] as [$label, $value, $colour])
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px 18px; display: flex; flex-direction: column; gap: 4px">
            <span style="font-size: 13px; color: #475569">{{ $label }}</span>
            <span style="font-family: 'Space Grotesk', sans-serif; font-size: 28px; font-weight: 700{{ $colour ? '; color: '.$colour : '' }}">{{ number_format($value) }}</span>
        </div>
    @endforeach
</section>
