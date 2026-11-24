{{-- Upload progress bar. Needs Alpine state { uploading, progress, failed } on an ancestor; see uploadProgressState(). --}}
<div x-show="uploading" x-cloak style="display: flex; flex-direction: column; gap: 4px">
    <div role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progress"
        style="height: 8px; border-radius: 999px; overflow: hidden; background: var(--sta-bg-selected, #E8EEF6)">
        <div x-bind:style="'height: 100%; border-radius: 999px; background: var(--sta-bg-brand, #1F3A5F); transition: width .2s; width: ' + progress + '%'"></div>
    </div>
    <span role="status" style="font-size: 12px; font-weight: 600; color: var(--sta-fg-brand, #1F3A5F)"
        x-text="progress < 100 ? 'Uploading… ' + progress + '% — please wait before saving' : 'Processing the file…'"></span>
</div>
<span x-show="failed" x-cloak role="alert" style="font-size: 12px; color: var(--sta-fg-red-700, #B91C1C)">The upload failed. Check the file type and size, then try again.</span>
