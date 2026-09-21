<?php

namespace App\Models;

use App\Enums\AuditEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Forward-only Ledger-Eintrag: Jede Zuweisungsänderung (inkl. Rollback)
 * wird als neues versioniertes Event angehängt – niemals gelöscht.
 *
 * @property int $id
 * @property int $shift_id
 * @property int $version
 * @property AuditEventType $event_type
 * @property array<string, mixed>|null $previous_state
 * @property array<string, mixed>|null $new_state
 * @property int|null $reverted_event_id
 * @property Carbon|null $created_at
 */
#[Fillable(['shift_id', 'version', 'event_type', 'previous_state', 'new_state', 'reverted_event_id'])]
class ShiftAuditEvent extends Model
{
    /**
     * Ledger-Semantik: Events sind immutable, daher kein updated_at.
     */
    public $timestamps = false;

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Das stornierte Event (nur bei Rollbacks gesetzt).
     */
    public function revertedEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverted_event_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => AuditEventType::class,
            'previous_state' => 'array',
            'new_state' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
