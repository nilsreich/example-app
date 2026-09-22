<?php

namespace App\Feedback\Models;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Feedback\Enums\FeedbackCategory;
use App\Feedback\Enums\FeedbackStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * In-App-Feedback (Marker.io-Stil, aber lokal): Meldung mit Seitenkontext,
 * optionalem privatem Screenshot und Triage-Status.
 *
 * @property int $id
 * @property int $user_id
 * @property FeedbackCategory $category
 * @property string $message
 * @property string $page_url
 * @property string|null $page_title
 * @property string|null $element_selector
 * @property string|null $element_text
 * @property array<string, mixed>|null $browser_info
 * @property string|null $screenshot_path
 * @property FeedbackStatus $status
 * @property string|null $resolution_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'category', 'message', 'page_url', 'page_title', 'element_selector', 'element_text', 'browser_info', 'screenshot_path', 'status', 'resolution_note'])]
class FeedbackReport extends Model
{
    /**
     * DSGVO: Beim Löschen einer Meldung wird der private Screenshot mit entfernt —
     * egal ob die Löschung über die Triage, purge() oder eine spätere Stellen landet.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $report): void {
            $report->deleteScreenshotFile();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FeedbackCategory::class,
            'status' => FeedbackStatus::class,
            'browser_info' => 'array',
        ];
    }

    /**
     * Statuswechsel der Triage: persistiert den Übergang UND schreibt ihn in das
     * GoBD-Audit (StatusChanged). Resolution-Notizen werden nur beim Abschluss
     * (Resolved) gesetzt bzw. geleert — Start/Weiter lassen sie unangetastet.
     */
    public function transitionTo(FeedbackStatus $status, ?string $resolutionNote = null, ?User $actor = null): self
    {
        // Direkt nach create() ist $status noch nicht hydriert (DB-Default 'new').
        $currentStatus = $this->status ?? FeedbackStatus::New;

        $newNote = $status === FeedbackStatus::Resolved ? $resolutionNote : $this->resolution_note;

        if ($status === $currentStatus && $newNote === $this->resolution_note) {
            return $this; // Kein tatsächlicher Wechsel → kein überflüssiger Audit-Eintrag.
        }

        $previousState = [
            'status' => $currentStatus->value,
            'resolution_note' => $this->resolution_note,
        ];

        $this->update([
            'status' => $status,
            'resolution_note' => $newNote,
        ]);

        app(AuditLedger::class)->record(
            AuditEventType::StatusChanged,
            $previousState,
            [
                'status' => $this->status->value,
                'resolution_note' => $this->resolution_note,
            ],
            auditable: $this,
            actor: $actor,
        );

        return $this;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasScreenshot(): bool
    {
        return $this->screenshot_path !== null;
    }

    /**
     * URL des privaten Screenshots (über Policy-geschützte Route,
     * nie direkt aus storage/app/private ausgeliefert).
     */
    public function screenshotUrl(): ?string
    {
        return $this->hasScreenshot() ? route('feedback.screenshot', $this) : null;
    }

    /**
     * Screenshot-Inhalt für die Auslieferungs-Route.
     */
    public function screenshotContents(): ?string
    {
        if (! $this->hasScreenshot()) {
            return null;
        }

        return Storage::disk('local')->get($this->screenshot_path);
    }

    /**
     * Löscht die Meldung samt privatem Screenshot (DSGVO: keine verwaisten Dateien).
     * Die Datei wird über den deleting-Hook entfernt.
     */
    public function purge(): bool
    {
        return $this->delete();
    }

    /**
     * Entfernt die Screenshot-Datei von der privaten Disk (falls vorhanden) und
     * setzt den Pfad im Modell zurück.
     */
    public function deleteScreenshotFile(): void
    {
        if (! $this->hasScreenshot()) {
            return;
        }

        Storage::disk('local')->delete($this->screenshot_path);
        $this->screenshot_path = null;
    }
}
