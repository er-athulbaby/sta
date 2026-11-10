<?php

namespace App\Enums;

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
}
