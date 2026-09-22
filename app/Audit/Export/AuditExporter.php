<?php

namespace App\Audit\Export;

use App\Audit\Enums\AuditEventType;
use App\Audit\Models\AuditEvent;
use App\Audit\Support\AuditChainVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Baut GoBD-taugliche Audit-Exporte (CSV/JSON) mit Filterung nach
 * Ereignistyp, Akteur, Objekttyp und Zeitraum. Jede Zeile trägt ihre
 * Hash-Kettenglieder (prev_hash, hash) plus – im JSON – den Kettengültigkeitsstatus.
 *
 * Die Ausgabe erfolgt zeilenweise über einen Stream (`writeCsv`/`writeJson`),
 * damit auch ein großes, monoton wachsendes Ledger ohne Speicheraufbau
 * exportiert werden kann. Die String-Varianten (`csv`/`json`) dienen Tests und
 * internen Aufrufern.
 */
final class AuditExporter
{
    /** @var list<string> */
    private const CSV_HEADERS = [
        'id', 'created_at', 'event_type', 'source', 'actor_user_id', 'actor_label',
        'auditable_type', 'auditable_id', 'version', 'ip', 'user_agent',
        'previous_state', 'new_state', 'prev_hash', 'hash',
    ];

    /** Führende Zeichen, die Tabellenkalkulationen als Formel interpretieren (OWASP). */
    private const CSV_FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

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

        $this->writeCsv($handle, $filters);

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Schreibt den CSV-Export zeilenweise in den übergebenen Stream.
     *
     * @param  resource  $handle
     * @param  array<string, mixed>  $filters
     */
    public function writeCsv($handle, array $filters = []): void
    {
        fputcsv($handle, self::CSV_HEADERS, ',', '"', '');

        foreach ($this->query($filters)->cursor() as $event) {
            $cells = array_map(
                static fn (mixed $value): string => self::csvCell(
                    is_array($value)
                        ? (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                        : (string) $value,
                ),
                $this->row($event),
            );
            fputcsv($handle, $cells, ',', '"', '');
        }
    }

    /**
     * JSON mit generated_at + Events inkl. chain_valid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function json(array $filters = []): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Temporären Export-Stream konnte nicht geöffnet werden.');
        }

        $this->writeJson($handle, $filters);

        rewind($handle);
        $json = (string) stream_get_contents($handle);
        fclose($handle);

        return $json;
    }

    /**
     * Schreibt den JSON-Export zeilenweise in den übergebenen Stream.
     *
     * @param  resource  $handle
     * @param  array<string, mixed>  $filters
     */
    public function writeJson($handle, array $filters = []): void
    {
        $context = AuditChainVerifier::context();

        fwrite($handle, '{"generated_at":'.self::jsonEncode(now()->toIso8601String()).',"events":[');

        $first = true;

        foreach ($this->query($filters)->cursor() as $event) {
            if (! $first) {
                fwrite($handle, ',');
            }

            fwrite($handle, self::jsonEncode([
                ...$this->row($event),
                'chain_valid' => AuditChainVerifier::evaluate($event, $context),
            ]));

            $first = false;
        }

        fwrite($handle, ']}');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditEvent>
     */
    private function query(array $filters): Builder
    {
        $query = AuditEvent::query()->orderByDesc('id');

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

        // Sargable Tagesgrenzen statt DATE(created_at) (nutzt den created_at-Index).
        $from = $filters['from'] ?? null;
        if (is_string($from) && $from !== '') {
            $query->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay());
        }

        $to = $filters['to'] ?? null;
        if (is_string($to) && $to !== '') {
            $query->where('created_at', '<=', CarbonImmutable::parse($to)->endOfDay());
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
            'actor_label' => $event->actor_label,
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

    /**
     * Neutralisiert CSV-Formel-Injektion: Zellen mit führendem Triggerzeichen
     * (= + - @ Tab CR) werden mit einem Apostroph präfixiert, damit
     * Excel/LibreOffice den Inhalt als Text und nicht als Formel auswerten.
     */
    private static function csvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], self::CSV_FORMULA_TRIGGERS, true)) {
            return "'".$value;
        }

        return $value;
    }

    private static function jsonEncode(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
