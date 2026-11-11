<?php

namespace App\Filament\Resources\Associations\Tables;

use App\Enums\Role;
use App\Filament\Shared\InvitationActions;
use App\Models\Association;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The list page draws the canvas AdminAssociations.dc.html table itself from these records; the columns here only
 * give the search ("Search by name or email") and the sort. The record actions are mounted from the canvas buttons.
 */
class AssociationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['users', 'sports' => fn ($s) => $s->orderBy('name')])
                ->withCount(['invitations as admin_invites_count' => fn ($i) => $i->pending()->where('role', Role::AssocAdmin)]))
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->searchable(['name', 'email'])->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                InvitationActions::invite('invite', Role::AssocAdmin,
                    association: fn (Association $record) => $record,
                    defaultEmail: fn (?Association $record) => $record?->email),
            ]);
    }

    /**
     * Canvas Admin cell: the active admin's name, else "Invite pending", else "No admin".
     * Needs `users` loaded and `admin_invites_count` (see configure()); falls back to queries otherwise.
     *
     * @return array{0: string, 1: 'active'|'pending'|'none'}
     */
    public static function adminState(Association $a): array
    {
        $admin = $a->users->first(fn ($u) => $u->role === Role::AssocAdmin && $u->is_active);
        if ($admin) {
            return [$admin->name, 'active'];
        }
        $pending = $a->admin_invites_count ?? $a->invitations()->pending()->where('role', Role::AssocAdmin)->count();

        return $pending ? ['Invite pending', 'pending'] : ['No admin', 'none'];
    }

    /** Active editors (canvas "n / 2"). */
    public static function editorCount(Association $a): int
    {
        return $a->users->filter(fn ($u) => $u->role === Role::AssocEditor && $u->is_active)->count();
    }
}
