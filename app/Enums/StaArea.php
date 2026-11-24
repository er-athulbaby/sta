<?php

namespace App\Enums;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The rows of the user-group permissions grid. Each applicable cell is a spatie permission "{area}.{action}"
 * (e.g. games.save) given to a group (a spatie role); a cell that does not apply to a row does not exist.
 * The Super Admin passes every check (Gate::before).
 */
enum StaArea: string
{
    case Dashboard = 'dashboard';
    case Games = 'games';
    case Submissions = 'submissions';
    case Associations = 'associations';
    case People = 'people';
    case Sports = 'sports';
    case ClothingItems = 'clothing_items';
    case Relationships = 'relationships';
    case ExtraFields = 'extra_fields';
    case Settings = 'settings';
    case Reports = 'reports';
    case GameFiles = 'game_files';
    case AuditLog = 'audit_log';
    case Users = 'users';
    // Special rights: one cell each.
    case Medical = 'medical';
    case Reminders = 'reminders';
    case EditAsSta = 'edit_as_sta';
    case GameSystemFiles = 'game_system_files';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::Games => 'Games (setup, phases, limits)',
            self::Submissions => 'Submissions (view, PDFs, remarks)',
            self::Associations => 'Associations & invitations',
            self::People => 'People (registry)',
            self::Sports => 'Sports & events',
            self::ClothingItems => 'Clothing items',
            self::Relationships => 'Relationships',
            self::ExtraFields => 'Extra fields',
            self::Settings => 'Settings',
            self::Reports => 'Reports (builder and exports)',
            self::GameFiles => 'Game files export (layouts)',
            self::AuditLog => 'Audit log',
            self::Users => 'Users & groups',
            self::Medical => 'Medical & health data',
            self::Reminders => 'Send reminders',
            self::EditAsSta => 'Edit as STA',
            self::GameSystemFiles => 'Game system files (passport numbers)',
        };
    }

    /** @return list<StaAction> the cells this row has */
    public function actions(): array
    {
        $a = StaAction::Access;
        $n = StaAction::New;
        $s = StaAction::Save;
        $d = StaAction::Delete;
        $p = StaAction::Print;

        return match ($this) {
            self::Dashboard, self::AuditLog, self::Medical, self::Reminders => [$a],
            self::Games, self::Sports, self::ClothingItems, self::Relationships, self::ExtraFields => [$a, $n, $s],
            self::Submissions => [$a, $s, $p],
            self::Associations, self::Users => [$a, $n, $s, $d],
            self::People, self::Reports => [$a, $n, $s, $d, $p],
            self::Settings, self::GameFiles => [$a, $s],
            self::EditAsSta => [$s],
            self::GameSystemFiles => [$p],
        };
    }

    public function isSpecial(): bool
    {
        return in_array($this, [self::Medical, self::Reminders, self::EditAsSta, self::GameSystemFiles], true);
    }

    public function permission(StaAction $action = StaAction::Access): string
    {
        return $this->value.'.'.$action->value;
    }

    /** The signed-in user (or $user) may do $action here. */
    public function can(StaAction $action = StaAction::Access, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return (bool) $user?->can($this->permission($action));
    }

    /** A Filament resource ability (viewAny, create, update, delete, ...) checked against this row; unknown abilities are denied. */
    public function authorize(string $ability): Response
    {
        $action = match ($ability) {
            'viewAny', 'view' => StaAction::Access,
            'create', 'replicate' => StaAction::New,
            'update', 'reorder', 'attach', 'associate', 'detach', 'detachAny', 'dissociate', 'dissociateAny' => StaAction::Save,
            'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny' => StaAction::Delete,
            default => null,
        };

        return $action && in_array($action, $this->actions(), true) ? Gate::inspect($this->permission($action)) : Response::deny();
    }

    /** @return list<string> every permission name of the grid */
    public static function allPermissions(): array
    {
        return collect(self::cases())->flatMap(fn (self $area) => array_map(fn (StaAction $a) => $area->permission($a), $area->actions()))->all();
    }

    /**
     * Normalises a set of ticked cells: unknown names are dropped and a row with New/Save/Delete/Print also gets Access
     * (when the row has an Access cell).
     *
     * @param  iterable<string>  $names
     * @return list<string>
     */
    public static function normalise(iterable $names): array
    {
        $valid = array_flip(self::allPermissions());
        $out = [];
        foreach ($names as $name) {
            if (! is_string($name) || ! isset($valid[$name])) {
                continue;
            }
            $out[$name] = true;
            $area = self::from(strstr($name, '.', true));
            if (in_array(StaAction::Access, $area->actions(), true)) {
                $out[$area->permission()] = true;
            }
        }

        return array_values(array_intersect(self::allPermissions(), array_keys($out)));
    }
}
