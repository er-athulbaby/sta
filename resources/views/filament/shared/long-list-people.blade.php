{{-- Long List people table. $rows = LongListChecks::rows(); optional $showTravel adds the travel action (host page must define the `travel` action). --}}
@if ($rows->isEmpty())
    <p class="sta-card-body sta-muted">Nobody on the list yet.</p>
@else
    <div class="sta-scroll">
        <table class="sta-table">
            <thead>
                <tr>
                    <th scope="col">Person</th><th scope="col">Entered in</th><th scope="col">Age</th><th scope="col">Registry</th><th scope="col">Warnings</th>
                    <th scope="col">@if ($showDetails ?? false) Travel & insurance @else <span class="sta-sr-only">Travel and insurance</span> @endif</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php $p = $row['person']; @endphp
                    <tr wire:key="person-{{ $p->id }}">
                        <td>
                            <div class="sta-who">
                                <x-sta.person-avatar :name="$p->fullName()" />
                                <div class="sta-who-text"><span class="sta-who-name">{{ $p->fullName() }}</span><span class="sta-who-sub">{{ $p->code }}</span></div>
                            </div>
                        </td>
                        <td class="sta-entered">{{ implode(', ', $row['roles']) }}</td>
                        <td>{{ $row['age'] }}</td>
                        <td>
                            @if ($row['missing'])
                                <x-sta.pill status="warn">Incomplete</x-sta.pill>
                                <div class="sta-who-sub">Missing: {{ implode(', ', $row['missing']) }}</div>
                            @else
                                <x-sta.pill status="ok">Complete</x-sta.pill>
                            @endif
                        </td>
                        <td><ul class="sta-warn-list">@foreach ($row['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></td>
                        <td class="sta-who-sub">
                            @if (($showDetails ?? false) && ($d = $row['detail']))
                                <div>{{ $d->insurance_company ?? '–' }}@if ($showMedical ?? false) · Policy {{ $d->insurance_policy_no ?? '–' }}@endif</div>
                                <div>Valid until {{ $d->insurance_valid_until?->format('j M Y') ?? '–' }}</div>
                                @if ($d->insurance_certificate_path)
                                    <a href="{{ \App\Filament\Shared\AttachmentActions::url($d->insurance_certificate_path) }}" target="_blank">View certificate</a>
                                @endif
                                @if ($d->vaccinations && ($showMedical ?? false))<div>Vaccinations: {{ $d->vaccinations }}</div>@endif
                            @elseif ($row['detail'])
                                <div>Insurance: {{ $row['detail']->insurance_company ?? '–' }}</div>
                                @if ($row['detail']->insurance_certificate_path)
                                    <a href="{{ \App\Filament\Shared\AttachmentActions::url($row['detail']->insurance_certificate_path) }}" target="_blank">View certificate</a>
                                @endif
                            @endif
                            @if (($showTravel ?? false) && $row['on_list'])
                                <x-filament::link tag="button" size="sm" wire:click="mountAction('travel', { person: {{ $p->id }} })">Travel & insurance</x-filament::link>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
