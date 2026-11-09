<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\InvitationMail;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class InvitationService
{
    public const EXPIRY_DAYS = 7;

    public function __construct(private readonly AssociationMembership $membership) {}

    /** Creates an invitation, emails it and returns the accept URL (also shown to the admin for copying). */
    public function invite(Association $association, string $email, Role $role, ?User $invitedBy): string
    {
        if (! $role->isAssociationRole()) {
            throw new InvalidArgumentException('Only association roles can be invited.');
        }
        $email = Str::lower(trim($email));

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
        }

        return DB::transaction(function () use ($association, $email, $role, $invitedBy) {
            Invitation::pending()->where('association_id', $association->id)->where('email', $email)
                ->update(['revoked_at' => now()]);

            $role === Role::AssocEditor
                ? $this->membership->ensureCanAddEditor($association)
                : $this->membership->ensureCanAddAdmin($association);

            $token = Str::random(48);
            $invitation = Invitation::create([
                'association_id' => $association->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $invitedBy?->id,
                'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            ]);

            $url = route('invitations.show', $token);
            Mail::to($email)->queue(new InvitationMail($invitation, $url));

            return $url;
        });
    }

    public function findPending(string $token): ?Invitation
    {
        return Invitation::pending()->where('token_hash', hash('sha256', $token))->first();
    }

    public function accept(string $token, string $name, string $password): User
    {
        return DB::transaction(function () use ($token, $name, $password) {
            $invitation = Invitation::pending()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $invitation) {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid or has expired.']);
            }
            if (User::where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['email' => 'An account with this email already exists. Please sign in instead.']);
            }

            $invitation->role === Role::AssocEditor
                ? $this->membership->ensureCanAddEditor($invitation->association, ignore: $invitation)
                : $this->membership->ensureCanAddAdmin($invitation->association, ignore: $invitation);

            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'role' => $invitation->role,
                'association_id' => $invitation->association_id,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }

    public function revoke(Invitation $invitation): void
    {
        $invitation->update(['revoked_at' => now()]);
    }
}
