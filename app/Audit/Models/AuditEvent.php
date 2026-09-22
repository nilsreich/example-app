<?php

namespace App\Audit\Models;

use App\Audit\Enums\AuditEventType;
use App\Audit\Enums\AuditSource;
use App\Audit\Support\HashChain;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Generischer, append-only Audit-Eintrag (GoBD-Ledger).
 *
 * @property int $id
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property int|null $actor_user_id
 * @property string|null $actor_label
 * @property AuditEventType $event_type
 * @property array<string, mixed>|null $previous_state
 * @property array<string, mixed>|null $new_state
 * @property int $version
 * @property AuditSource $source
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $prev_hash
 * @property string $hash
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['auditable_type', 'auditable_id', 'actor_user_id', 'actor_label', 'event_type', 'previous_state', 'new_state', 'version', 'source', 'ip', 'user_agent', 'prev_hash', 'hash'])]
class AuditEvent extends Model
{
    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    /**
     * Ledger-Semantik: Events sind immutable; Updates/Löschungen werden verhindert.
     */
    protected static function booted(): void
    {
        static::saving(function (self $event): void {
            if ($event->exists) {
                throw new \RuntimeException('Audit events are append-only; updates are not allowed.');
            }
        });

        static::deleting(function (self $event): void {
            throw new \RuntimeException('Audit events are append-only; deletion is not allowed.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
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
            'source' => AuditSource::class,
            'previous_state' => 'array',
            'new_state' => 'array',
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Kanonischer Hash-Eingang für diesen Block (ohne prev_hash/hash/id).
     * Enthält created_at, damit auch der Zeitstempel manipulationsfest ist.
     * Muss zur Laufzeit stabil bleiben, sonst brechen bestehende Ketten.
     *
     * @return array<string, mixed>
     */
    public function blockPayload(): array
    {
        return [
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'actor_label' => $this->actor_label,
            'event_type' => $this->event_type->value,
            'previous_state' => $this->previous_state ?? [],
            'new_state' => $this->new_state ?? [],
            'version' => $this->version,
            'source' => $this->source->value,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Verifiziert diesen Block gegen die gespeicherte Hash-Kette.
     * False = Inhalt oder Verkettung wurde nachträglich verändert.
     */
    public function isChainValid(): bool
    {
        return HashChain::verify($this->prev_hash, $this->blockPayload(), $this->hash);
    }
}
