<?php

namespace App\Audit\Support;

use App\Audit\Models\AuditEvent;
use Illuminate\Support\Facades\DB;

/**
 * DB-seitige Append-only-Sicherung des Audit-Ledgers.
 *
 * Der Eloquent-Guard in {@see AuditEvent} ist nur die erste
 * Schicht: Query-Builder- und Raw-SQL-Zugriffe umgehen ihn. Diese Trigger
 * verhindern UPDATE/DELETE auf DB-Ebene. Implementiert für SQLite (Tests,
 * lokale Entwicklung) und MySQL (Produktion). Andere Treiber erhalten keinen
 * Trigger – dort bleibt der Eloquent-Guard die einzige Schicht.
 */
final class AuditGuards
{
    /** @var list<string> */
    private const TRIGGERS = ['audit_events_no_update', 'audit_events_no_delete'];

    public static function enable(): void
    {
        self::disable();

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events
                BEGIN
                    SELECT RAISE(ABORT, 'audit_events is append-only (update)');
                END
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events
                BEGIN
                    SELECT RAISE(ABORT, 'audit_events is append-only (delete)');
                END
                SQL);

            return;
        }

        if ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append-only (update)'");
            DB::unprepared("CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append-only (delete)'");
        }
    }

    public static function disable(): void
    {
        foreach (self::TRIGGERS as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
}
