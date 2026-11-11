{{-- Canvas PlayerForm / OfficialForm file field for a Filament FileUpload: the photo column ($variant "photo") or a file row
     (name, "TYPE · size · date", View through the 5-minute link, Replace), or a dashed upload button when empty. The file goes
     into the FileUpload's own state ({statePath}.{key}), so Filament's validation and storage are unchanged. --}}
@php
    use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

    $statePath = $getStatePath();
    // Replace keeps the stored entry until the new upload has arrived (then removes it), so a failed upload never empties
    // the field; while both are present, show the new one.
    $values = collect(\Illuminate\Support\Arr::wrap($field->getRawState()))->filter();
    $value = $values->first(fn ($v) => $v instanceof TemporaryUploadedFile) ?? $values->first();
    $disabled = $isDisabled();
    $inputId = $getId();
    $human = fn (?int $bytes) => $bytes === null ? null : ($bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB');
    $file = null;
    if ($value instanceof TemporaryUploadedFile) {
        $file = ['name' => $value->getClientOriginalName(), 'size' => $human($value->getSize()), 'date' => 'not saved yet', 'url' => null,
            'preview' => $value->isPreviewable() ? $value->temporaryUrl() : null];
    } elseif (is_string($value) && $field->isFilePathAuthorized($value)) {
        $disk = $field->getDisk();
        try {
            $size = $disk->size($value);
            $date = \Illuminate\Support\Carbon::createFromTimestamp($disk->lastModified($value))->format('j M Y');
        } catch (\Throwable) {
            $size = $date = null;
        }
        $url = \App\Filament\Shared\AttachmentActions::url($value);
        $file = ['name' => basename($value), 'size' => $human($size), 'date' => $date, 'url' => $url,
            'preview' => in_array(strtolower(pathinfo($value, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true) ? $url : null];
    }
    $type = $file ? strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)) : null;
    $meta = $file ? implode(' · ', array_filter([$type, $file['size'], $file['date']])) : null;
    $accept = implode(',', $field->getAcceptedFileTypes() ?? []);
    $person = $field->getRecord();
    $btn = 'min-height: 44px; padding: 0 12px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 13px Inter, sans-serif; display: inline-flex; align-items: center; cursor: pointer; box-sizing: border-box';
    $link = 'display: inline-flex; align-items: center; min-height: 44px; padding: 0 4px; font-weight: 600; font-size: 13px';
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{ uploading: false, failed: false }" style="display: flex; flex-direction: column; gap: 10px">
        @unless ($disabled)
            <input type="file" id="{{ $inputId }}" accept="{{ $accept }}" class="sta-sr-only" tabindex="-1"
                x-on:change="
                    const f = $event.target.files[0];
                    if (! f) return;
                    uploading = true; failed = false;
                    const old = Object.keys($wire.get(@js($statePath)) || {});
                    $wire.upload(@js($statePath) + '.' + Math.random().toString(36).slice(2) + Date.now().toString(36), f,
                        async () => {
                            for (const fileKey of old) {
                                await $wire.callSchemaComponentMethod(@js($getKey()), 'removeUploadedFile', { fileKey });
                            }
                            uploading = false;
                            $wire.$refresh();
                        },
                        () => { uploading = false; failed = true });
                    $event.target.value = '';
                ">
        @endunless

        @if ($variant === 'photo')
            @if ($file && $file['preview'])
                <img src="{{ $file['preview'] }}" alt="Photo preview: {{ $person?->fullName() }}" style="width: 160px; height: 200px; border-radius: 12px; object-fit: cover; background: #E8EEF6">
            @else
                <div role="img" aria-label="Photo preview: {{ $person?->fullName() ?? 'no photo yet' }}" style="width: 160px; height: 200px; border-radius: 12px; background: #E8EEF6; color: #1F3A5F; display: flex; align-items: center; justify-content: center; font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 40px">{{ $person ? \App\Support\Sta::initials($person->fullName()) : '' }}</div>
            @endif
            @if ($file)
                <span style="font-size: 12px; color: #334155; overflow-wrap: anywhere">{{ $file['name'] }}@if ($file['size']) · {{ $file['size'] }}@endif</span>
            @endif
            <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center">
                @if ($file && $file['url'])
                    <a href="{{ $file['url'] }}" target="_blank" rel="noopener" aria-label="View photo" style="{{ $link }}">View</a>
                @endif
                @unless ($disabled)
                    <label for="{{ $inputId }}" tabindex="0" role="button" class="sta-upload" x-on:keydown.enter.prevent="$el.click()" x-on:keydown.space.prevent="$el.click()" style="{{ $btn }}">{{ $file ? 'Replace' : 'Upload' }}<span class="sta-sr-only"> photo</span></label>
                @endunless
            </div>
        @elseif ($file)
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; min-height: 52px; padding: 6px 12px; box-sizing: border-box; border-radius: 10px; border: 1px solid #E2E8F0; background: #F8FAFC">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1F3A5F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0"><path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4"/></svg>
                <div style="flex: 1 1 140px; min-width: 0; display: flex; flex-direction: column">
                    <span style="font-weight: 600; font-size: 13px; overflow-wrap: anywhere">{{ $file['name'] }}</span>
                    <span style="font-size: 12px; color: #475569">{{ $meta }}</span>
                </div>
                @if ($file['url'])
                    <a href="{{ $file['url'] }}" target="_blank" rel="noopener" aria-label="View {{ $getLabel() }}" style="{{ $link }}">View</a>
                @endif
                @unless ($disabled)
                    <label for="{{ $inputId }}" tabindex="0" role="button" class="sta-upload" x-on:keydown.enter.prevent="$el.click()" x-on:keydown.space.prevent="$el.click()" style="{{ $btn }}">Replace<span class="sta-sr-only"> {{ $getLabel() }}</span></label>
                @endunless
            </div>
        @elseif (! $disabled)
            <label for="{{ $inputId }}" tabindex="0" role="button" class="sta-upload" x-on:keydown.enter.prevent="$el.click()" x-on:keydown.space.prevent="$el.click()"
                style="align-self: stretch; display: flex; align-items: center; gap: 10px; min-height: 52px; padding: 0 12px; box-sizing: border-box; border-radius: 10px; border: 1px dashed #94A3B8; background: #FFFFFF; color: #334155; font: 600 13px Inter, sans-serif; cursor: pointer">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="flex-shrink: 0"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg>
                {{ $uploadLabel }}
            </label>
        @else
            <span style="font-size: 13px; color: #475569">No file</span>
        @endif
        <template x-if="uploading"><span role="status" style="font-size: 12px; color: #475569">Uploading…</span></template>
        <template x-if="failed"><span role="alert" style="font-size: 12px; color: #B91C1C">The upload failed. Check the file type and size, then try again.</span></template>
    </div>
</x-dynamic-component>
