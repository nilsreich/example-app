<?php

namespace App\Audit\Export;

use App\Audit\Enums\AuditEventType;
use App\Audit\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Baut GoBD-taugliche Audit-Exporte (CSV/JSON) mit Filterung nach
 * Ereignistyp, Akteur, Objekttyp und Zeitraum. Jede Zeile trägt ihre
 * Hash-Kettenglieder (prev_hash, hash) plus – im JSON – den Kettengültigkeitsstatus.
 */
final class AuditExporter
{
    /** @var list<string> */
    private const CSV_HEADERS = [
        'id', 'created_at', 'event_type', 'source', 'actor_user_id',
        'auditable_type', 'auditable_id', 'version', 'ip', 'user_agent',
        'previous_state', 'new_state', 'prev_hash', 'hash',
    ];

    /**
     * CSV mit Header-Zeile; Felder mit Sonderzeichen werden von fputcsv
     * korrekt gequotet/escaped.
     *
     * @param  array<string, mixed>  $filters
     */
    public function csv(array $filters = []): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Temporären Export-Stream konnte nicht geöffnet werden.');
        }

        fputcsv($handle, self::CSV_HEADERS);

        foreach ($this->events($filters) as $event) {
            $cells = array_map(
                static fn (mixed $value): mixed => is_array($value)
                    ? (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                    : $value,
                $this->row($event),
            );
            fputcsv($handle, $cells);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * JSON mit generated_at + Events inkl. chain_valid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function json(array $filters = []): string
    {
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'events' => $this->events($filters)
                ->map(fn (AuditEvent $event): array => [
                    ...$this->row($event),
                    'chain_valid' => $event->isChainValid(),
                ])
                ->all(),
        ];

        return (string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AuditEvent>
     */
    private function events(array $filters): Collection
    {
        return $this->query($filters)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditEvent>
     */
    private function query(array $filters): Builder
    {
        $query = AuditEvent::query()->with('actor')->orderByDesc('id');

        $eventType = $filters['event_type'] ?? null;
        if ($eventType instanceof AuditEventType) {
            $eventType = $eventType->value;
        }
        if (is_string($eventType) && $eventType !== '') {
            $query->where('event_type', $eventType);
        }

        $actor = $filters['actor'] ?? null;
        if (is_numeric($actor)) {
            $query->where('actor_user_id', (int) $actor);
        }

        $auditableType = $filters['auditable_type'] ?? null;
        if (is_string($auditableType) && $auditableType !== '') {
            $query->where('auditable_type', $auditableType);
        }

        $from = $filters['from'] ?? null;
        if (is_string($from) && $from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        $to = $filters['to'] ?? null;
        if (is_string($to) && $to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AuditEvent $event): array
    {
        return [
            'id' => $event->id,
            'created_at' => $event->created_at?->toIso8601String(),
            'event_type' => $event->event_type->value,
            'source' => $event->source->value,
            'actor_user_id' => $event->actor_user_id,
            'auditable_type' => $event->auditable_type,
            'auditable_id' => $event->auditable_id,
            'version' => $event->version,
            'ip' => $event->ip,
            'user_agent' => $event->user_agent,
            'previous_state' => $event->previous_state ?? [],
            'new_state' => $event->new_state ?? [],
            'prev_hash' => $event->prev_hash,
            'hash' => $event->hash,
        ];
    }
}
