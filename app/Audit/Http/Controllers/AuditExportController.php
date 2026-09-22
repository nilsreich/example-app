<?php

namespace App\Audit\Http\Controllers;

use App\Audit\Export\AuditExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Liefert Audit-Exporte (CSV/JSON) erst nach Berechtigungsprüfung aus.
 * Die Daten enthalten personenbezogene Änderungshistorie – deshalb:
 * nur für audit-berechtigte Rollen, immer als Attachment, nie im Klartext geroutet.
 */
final class AuditExportController
{
    public function __invoke(Request $request): Response
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

        unset($validated['format']);

        $payload = app(AuditExporter::class);

        if (($request->string('format')->toString() ?: 'csv') === 'json') {
            return response($payload->json($validated), 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="audit-export.json"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response($payload->csv($validated), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit-export.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
