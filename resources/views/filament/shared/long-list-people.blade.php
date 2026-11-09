{{-- Long List people table. $rows = LongListChecks::rows(); optional $showTravel adds the travel action (host page must define the `travel` action). --}}
@if ($rows->isEmpty())
    <p class="text-sm text-gray-500">Nobody on the list yet.</p>
@else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500"><th class="py-1">Name</th><th>Entered in</th><th>Age</th><th>Registry</th><th>Warnings</th><th>{{ ($showDetails ?? false) ? 'Travel & insurance' : '' }}</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr wire:key="person-{{ $row['person']->id }}" class="border-t border-gray-200 align-top dark:border-white/10">
                        <td class="py-2">{{ $row['person']->fullName() }} <span class="text-xs text-gray-500">{{ $row['person']->code }}</span></td>
                        <td class="py-2">{{ implode(', ', $row['roles']) }}</td>
                        <td class="py-2">{{ $row['age'] }}</td>
                        <td class="py-2">
                            @if ($row['missing'])
                                <x-filament::badge color="danger">Incomplete</x-filament::badge>
                                <div class="text-xs text-gray-500">Missing: {{ implode(', ', $row['missing']) }}</div>
                            @else
                                <x-filament::badge color="success">Complete</x-filament::badge>
                            @endif
                        </td>
                        <td class="py-2">
                            <ul class="text-xs text-warning-600">
                                @foreach ($row['warnings'] as $w)<li>{{ $w }}</li>@endforeach
                            </ul>
                        </td>
                        <td class="py-2">
                            @if (($showDetails ?? false) && ($d = $row['detail']))
                                <div class="text-xs">
                                    <div>{{ $d->insurance_company ?? '–' }}@if ($showMedical ?? false) · Policy {{ $d->insurance_policy_no ?? '–' }}@endif</div>
                                    <div>Valid until {{ $d->insurance_valid_until?->format('j M Y') ?? '–' }}</div>
                                    @if ($d->insurance_certificate_path)
                                        <a href="{{ \App\Filament\Shared\AttachmentActions::url($d->insurance_certificate_path) }}" target="_blank" class="text-primary-600 underline">Certificate</a>
                                    @endif
                                    @if ($d->vaccinations)<div>Vaccinations: {{ $d->vaccinations }}</div>@endif
                                </div>
                            @elseif ($row['detail'])
                                <div class="text-xs text-gray-500">Insurance: {{ $row['detail']->insurance_company ?? '–' }}</div>
                            @endif
                            @if (($showTravel ?? false) && $row['on_list'])
                                <x-filament::link tag="button" size="xs" wire:click="mountAction('travel', { person: {{ $row['person']->id }} })">Travel & insurance</x-filament::link>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
@endif
