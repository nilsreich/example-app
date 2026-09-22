<?php

namespace App\Audit\Enums;

enum AuditEventType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case StatusChanged = 'status_changed';
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case Provisioned = 'provisioned';
    case RoleChanged = 'role_changed';
    case DepartmentChanged = 'department_changed';
    case AiDecision = 'ai_decision';
    case Rollback = 'rollback';
    case Exported = 'exported';
    case SettingsChanged = 'settings_changed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Angelegt',
            self::Updated => 'Aktualisiert',
            self::Deleted => 'Gelöscht',
            self::StatusChanged => 'Status geändert',
            self::Login => 'Anmeldung',
            self::Logout => 'Abmeldung',
            self::LoginFailed => 'Anmeldung fehlgeschlagen',
            self::Provisioned => 'Bereitgestellt',
            self::RoleChanged => 'Rolle geändert',
            self::DepartmentChanged => 'Abteilung geändert',
            self::AiDecision => 'KI-Entscheidung',
            self::Rollback => 'Rücknahme',
            self::Exported => 'Export',
            self::SettingsChanged => 'Einstellungen geändert',
        };
    }
}
