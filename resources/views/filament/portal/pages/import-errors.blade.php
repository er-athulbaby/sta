@php($rowErrors = $getLivewire()->rowErrors)
@if ($rowErrors)
    <x-filament::section heading="Rows with errors">
        <table class="w-full text-sm">
            <thead><tr><th class="text-left p-2">Row</th><th class="text-left p-2">Problems</th></tr></thead>
            <tbody>
            @foreach ($rowErrors as $row => $messages)
                <tr class="border-t"><td class="p-2 align-top">{{ $row }}</td><td class="p-2"><ul>@foreach ($messages as $m)<li>{{ $m }}</li>@endforeach</ul></td></tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::section>
@endif
