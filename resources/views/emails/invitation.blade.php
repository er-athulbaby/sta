<x-mail::message>
# You are invited

You have been invited to join **{{ $invitation->association->name }}** on the Sports Entry Portal as **{{ $invitation->role->label() }}**.

<x-mail::button :url="$url">Accept invitation</x-mail::button>

This link can be used once and expires on {{ \App\Support\Sta::date($invitation->expires_at) }}.
</x-mail::message>
