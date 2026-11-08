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
            ->action(function (array $data, ?Association $record) use ($role, $association) {
                self::guard(function () use ($data, $record, $role, $association) {
                    $url = app(InvitationService::class)->invite($association($record), $data['email'], $role, auth()->user());
                    self::linkNotification($url);
                });
            });
    }

    public static function resend(): Action
    {
        return Action::make('resend')->icon('heroicon-o-arrow-path')
            ->visible(fn (Invitation $record) => in_array($record->status(), ['pending', 'expired'], true))
            ->action(fn (Invitation $record) => self::guard(function () use ($record) {
                self::linkNotification(app(InvitationService::class)->invite($record->association, $record->email, $record->role, auth()->user()));
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

    private static function linkNotification(string $url): void
    {
        Notification::make()->success()->persistent()
            ->title('Invitation sent')
            ->body("The invitation email has been sent. You can also share this link (valid 7 days, single use):\n{$url}")
            ->send();
    }
}
