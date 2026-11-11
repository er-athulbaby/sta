<?php

namespace App\Filament\Shared;

use App\Enums\Role;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use App\Services\AssociationMembership;
use App\Services\InvitationService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InvitationActions
{
    /** @param Closure(?Association): Association $association resolves the target association */
    public static function invite(string $name, Role $role, Closure $association, ?Closure $defaultEmail = null): Action
    {
        return Action::make($name)
            ->label($role === Role::AssocAdmin ? 'Invite admin' : 'Invite editor')
            ->icon('heroicon-o-envelope')
            ->schema([TextInput::make('email')->email()->required()->default($defaultEmail)])
            ->action(function (array $data, ?Association $record, $livewire) use ($role, $association) {
                self::guard(function () use ($data, $record, $role, $association, $livewire) {
                    $target = $association($record);
                    $url = app(InvitationService::class)->invite($target, $data['email'], $role, auth()->user());
                    self::linkNotification($url, $livewire, $target->id, $data['email']);
                });
            });
    }

    public static function resend(): Action
    {
        return Action::make('resend')->icon('heroicon-o-arrow-path')
            ->visible(fn (Invitation $record) => in_array($record->status(), ['pending', 'expired'], true))
            ->action(fn (Invitation $record, $livewire) => self::guard(function () use ($record, $livewire) {
                self::linkNotification(app(InvitationService::class)->invite($record->association, $record->email, $record->role, auth()->user()), $livewire, $record->association_id, $record->email);
            }));
    }

    public static function revoke(): Action
    {
        return Action::make('revoke')->color('danger')->requiresConfirmation()
            ->visible(fn (Invitation $record) => $record->isPending())
            ->action(fn (Invitation $record) => app(InvitationService::class)->revoke($record));
    }

    public static function setActive(bool $active): Action
    {
        return Action::make($active ? 'activate' : 'deactivate')
            ->color($active ? 'success' : 'danger')
            ->requiresConfirmation()
            ->visible(fn (User $record) => $record->is_active !== $active && $record->id !== auth()->id())
            ->action(fn (User $record) => self::guard(fn () => app(AssociationMembership::class)->setActive($record, $active)));
    }

    private static function guard(Closure $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
        }
    }

    private static function linkNotification(string $url, mixed $livewire = null, ?int $associationId = null, ?string $email = null): void
    {
        // Pages with an `inviteLinks` map (canvas Associations "Invitation link" + Copy) keep the new link for this
        // page visit only: the token is stored hashed, so a link can never be shown again later.
        // Keyed by association too: the same email can be invited by two associations.
        if ($associationId !== null && $email !== null && is_object($livewire) && property_exists($livewire, 'inviteLinks')) {
            $livewire->inviteLinks[$associationId.':'.Str::lower(trim($email))] = $url;
        }
        Notification::make()->success()->persistent()
            ->title('Invitation sent')
            ->body("The invitation email has been sent. You can also share this link (valid 7 days, single use):\n{$url}")
            ->send();
    }
}
