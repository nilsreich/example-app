<?php

namespace App\Audit\Http\Controllers;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Audit\Export\AuditExporter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liefert Audit-Exporte (CSV/JSON) erst nach Berechtigungsprüfung aus.
 * Die Daten enthalten personenbezogene Änderungshistorie – deshalb:
 * nur für audit-berechtigte Rollen, immer als Attachment, nie im Klartext geroutet.
 *
 * Der Export wird zeilenweise gestreamt (kein vollständiger Aufbau im Speicher)
 * und als `exported`-Event im Ledger protokolliert (GoBD: Zugriffsnachweis).
 */
final class AuditExportController
{
    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('audit.export');

        $validated = $request->validate([
            'format' => ['sometimes', 'string', 'in:csv,json'],
            'event_type' => ['sometimes', 'string', 'max:50'],
            'actor' => ['sometimes', 'integer'],
            'auditable_type' => ['sometimes', 'string', 'max:255'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $format = $request->string('format')->toString() ?: 'csv';
        unset($validated['format']);

        $actor = $request->user();
        $actor = $actor instanceof User ? $actor : null;

        // Zugriff revisionssicher protokollieren (vor der Auslieferung).
        app(AuditLedger::class)->record(
            eventType: AuditEventType::Exported,
            previousState: [],
            newState: ['format' => $format, 'filters' => $validated],
            actor: $actor,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $exporter = app(AuditExporter::class);

        if ($format === 'json') {
            return response()->streamDownload(
                function () use ($exporter, $validated): void {
                    $handle = fopen('php://output', 'w');

                    if ($handle === false) {
                        return;
                    }

                    $exporter->writeJson($handle, $validated);
                },
                'audit-export.json',
                [
                    'Content-Type' => 'application/json',
                    'Cache-Control' => 'private, no-store',
                ],
            );
        }

        return response()->streamDownload(
            function () use ($exporter, $validated): void {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                $exporter->writeCsv($handle, $validated);
            },
            'audit-export.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
