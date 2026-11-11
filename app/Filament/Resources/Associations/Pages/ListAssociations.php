<?php

namespace App\Filament\Resources\Associations\Pages;

use App\Enums\Role;
use App\Filament\Resources\Associations\AssociationResource;
use App\Filament\Shared\InvitationActions;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\View\View as ViewContract;

/**
 * Canvas AdminAssociations.dc.html: the list (drawn from the Filament table's records, so search stays Filament's) and
 * the selected association's aside (details, Users, Invitations). The aside buttons mount the existing actions.
 */
class ListAssociations extends ListRecords
{
    protected static string $resource = AssociationResource::class;

    /** The association the aside shows; null = the first row. */
    public ?int $selected = null;

    /** @var array<string, string> invitation links sent from this page visit, by email (see InvitationActions). */
    public array $inviteLinks = [];

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sta-canvas-page'];
    }

    public function getHeader(): ?ViewContract
    {
        return view('filament.resources.associations.list-header');
    }

    public function getSubheading(): ?string
    {
        $total = Association::count();
        $withAdmin = Association::whereHas('users', fn ($q) => $q->where('role', Role::AssocAdmin)->where('is_active', true))->count();

        return "{$total} associations · {$withAdmin} have an active admin · ".Invitation::pending()->count().' invitations pending';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([View::make('filament.resources.associations.list')]);
    }

    public function selectAssociation(int $id): void
    {
        $this->selected = $id;
    }

    #[Unexposed]
    public function panelAssociation(): ?Association
    {
        $records = $this->getTableRecords();
        $a = $records->firstWhere('id', $this->selected) ?? ($this->selected ? Association::find($this->selected) : null) ?? $records->first();

        $a?->load(['users', 'invitations' => fn ($q) => $q->pending()->latest('id')]);
        // Admin first, then editors (canvas order).
        $a?->setRelation('users', $a->users->sortBy(fn (User $u) => [$u->role === Role::AssocAdmin ? 0 : 1, $u->name])->values());

        return $a;
    }

    public function deactivateUserAction(): Action
    {
        return InvitationActions::setActive(false)->name('deactivateUser')->label('Deactivate')->record(fn (array $arguments) => $this->associationUser($arguments));
    }

    public function activateUserAction(): Action
    {
        return InvitationActions::setActive(true)->name('activateUser')->label('Activate')->record(fn (array $arguments) => $this->associationUser($arguments));
    }

    public function resendInviteAction(): Action
    {
        return InvitationActions::resend()->name('resendInvite')->label('Resend email')
            ->record(fn (array $arguments) => Invitation::query()->findOrFail($arguments['id'] ?? 0));
    }

    public function revokeInviteAction(): Action
    {
        return InvitationActions::revoke()->name('revokeInvite')->label('Revoke')
            ->record(fn (array $arguments) => Invitation::query()->findOrFail($arguments['id'] ?? 0));
    }

    /** Only association users (never STA accounts) can be (de)activated from this page. */
    private function associationUser(array $arguments): User
    {
        return User::query()->whereNotNull('association_id')
            ->whereIn('role', [Role::AssocAdmin, Role::AssocEditor])->findOrFail($arguments['id'] ?? 0);
    }
}
