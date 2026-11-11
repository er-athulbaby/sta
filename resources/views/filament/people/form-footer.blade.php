{{-- Canvas bottom buttons: Cancel / Save as draft (submits the form) / Save (saves and returns to the list). --}}
<div style="display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px">
    <a href="{{ static::getResource()::getUrl('index') }}" style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box">Cancel</a>
    <button type="submit" wire:loading.attr="disabled" style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 14px Inter, sans-serif; cursor: pointer">Save as draft</button>
    <button type="button" wire:click="saveAndClose" wire:loading.attr="disabled" style="min-height: 44px; padding: 0 20px; border-radius: 10px; border: 0; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; cursor: pointer">Save</button>
</div>
