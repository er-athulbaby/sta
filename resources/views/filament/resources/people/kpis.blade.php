<section aria-label="Summary" class="sta-grid-kpi">
    <x-sta.kpi label="Athletes" :value="number_format($kpis['athletes'])" />
    <x-sta.kpi label="Team officials" :value="number_format($kpis['officials'])" />
    <x-sta.kpi label="Passport expires within 6 months" :value="number_format($kpis['expiring'])" tone="warn" />
    <x-sta.kpi label="Missing passport scan" :value="number_format($kpis['missingScan'])" tone="warn" />
</section>
