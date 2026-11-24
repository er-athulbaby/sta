<?php

namespace App\Enums;

/**
 * The 11 on/off rights STA users had before user groups, kept for the migration and as test shorthand. Each one now
 * stands for the group-grid cells in grants(); no code checks these names any more.
 */
enum StaPermission: string
{
    case ManageAssociations = 'manage_associations';
    case ManageMasterData = 'manage_master_data';
    case ManageGames = 'manage_games';
    case ViewSubmissions = 'view_submissions';
    case EditSubmissions = 'edit_submissions';
    case ManagePeople = 'manage_people';
    case SendReminders = 'send_reminders';
    case ViewReports = 'view_reports';
    case ViewAuditLog = 'view_audit_log';
    case ViewMedical = 'view_medical';
    case ExportGameFiles = 'export_game_files';

    public function label(): string
    {
        return match ($this) {
            self::ManageAssociations => 'Manage associations and invitations',
            self::ManageMasterData => 'Manage sports, events, clothing items and settings',
            self::ManageGames => 'Manage games, limits and phases',
            self::ViewSubmissions => 'View submissions and PDFs',
            self::EditSubmissions => 'Edit submissions and comment',
            self::ManagePeople => 'View and edit people of any association',
            self::SendReminders => 'Send "Remind pending"',
            self::ViewReports => 'View reports and export Excel',
            self::ViewAuditLog => 'View the audit log',
            self::ViewMedical => 'View medical & health data',
            self::ExportGameFiles => 'Export files for game systems — includes passport numbers',
        };
    }

    /** @return list<string> the grid permissions ({area}.{action}) that give the same access as this old right */
    public function grants(): array
    {
        $cells = fn (StaArea $area, StaAction ...$actions) => array_map(fn (StaAction $a) => $area->permission($a), $actions ?: $area->actions());
        $p = StaAction::Print;

        return match ($this) {
            self::ManageAssociations => $cells(StaArea::Associations),
            self::ManageMasterData => [...$cells(StaArea::Sports), ...$cells(StaArea::ClothingItems), ...$cells(StaArea::Relationships),
                ...$cells(StaArea::ExtraFields), ...$cells(StaArea::Settings)],
            self::ManageGames => $cells(StaArea::Games),
            self::ViewSubmissions => $cells(StaArea::Submissions, StaAction::Access, $p),
            self::EditSubmissions => [...$cells(StaArea::Submissions, StaAction::Save), ...$cells(StaArea::EditAsSta)],
            // Deleting people was Super Admin only: not part of the old right.
            self::ManagePeople => $cells(StaArea::People, StaAction::Access, StaAction::New, StaAction::Save, $p),
            self::SendReminders => $cells(StaArea::Reminders),
            self::ViewReports => $cells(StaArea::Reports),
            self::ViewAuditLog => $cells(StaArea::AuditLog),
            self::ViewMedical => $cells(StaArea::Medical),
            self::ExportGameFiles => [...$cells(StaArea::GameFiles), ...$cells(StaArea::GameSystemFiles)],
        };
    }

    /**
     * The grid permissions of several old rights together, plus the Dashboard every STA user had.
     *
     * @param  iterable<self|string>  $rights
     * @return list<string>
     */
    public static function grantsOf(iterable $rights): array
    {
        $names = [StaArea::Dashboard->permission()];
        foreach ($rights as $r) {
            array_push($names, ...($r instanceof self ? $r : self::from($r))->grants());
        }

        return StaArea::normalise($names);
    }
}
