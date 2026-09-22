<?php

namespace App\Audit\Concerns;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use Illuminate\Database\Eloquent\Model;

/**
 * Macht ein Modell auditierbar: Erstellen, Aktualisieren und Löschen werden
 * automatisch über den AuditLedger im Forward-Ledger (GoBD) protokolliert.
 *
 * Anwenden: `use Auditable;` im Modell.
 *
 * Hinweis: Das True-Ledger-Erstellen der Events läuft über den AuditLedger;
 * das Modell selbst darf den Auditable-Trait nicht verwenden.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(static function (Model $model): void {
            app(AuditLedger::class)->record(
                eventType: AuditEventType::Created,
                previousState: [],
                newState: $model->getAttributes(),
                auditable: $model,
            );
        });

        static::updated(static function (Model $model): void {
            app(AuditLedger::class)->record(
                eventType: AuditEventType::Updated,
                previousState: $model->getOriginal(),
                newState: $model->getAttributes(),
                auditable: $model,
            );
        });

        static::deleted(static function (Model $model): void {
            app(AuditLedger::class)->record(
                eventType: AuditEventType::Deleted,
                previousState: $model->getAttributes(),
                newState: [],
                auditable: $model,
            );
        });
    }
}
