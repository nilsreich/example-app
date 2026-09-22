<?php

namespace App\Livewire;

use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDecision;
use App\Ai\Services\AiDecisionAuditor;
use App\Enums\FeedbackRating;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftFeedback;
use App\Models\ShiftOptimization;
use App\Models\ShiftProposal;
use App\Services\ShiftOptimizationRunner;
use App\Services\ShiftProposalAcceptService;
use App\Services\ShiftRollbackService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Dispatching-Slide-Over: Pipeline-Lauf, Match-Anzeige mit Konfidenz,
 * Feedback, 1-Klick-Zuweisung und Forward-Rollback – alles revisionssicher.
 */
class ShiftDispatch extends Component
{
    /**
     * Feedback-Kategorien bei Daumen runter (Pflichtfeld im Modal).
     */
    public const FEEDBACK_CATEGORIES = ['Regelkonflikt', 'Mitarbeiter nicht erreichbar', 'Sonstiges'];

    public int $shiftId;

    /**
     * Anzeige-Selektor des aktuellen Laufs. Nicht #[Locked], weil der Wert nach
     * einem asynchronen Queue-Lauf per Polling wechselt – jede Verwendung wird
     * stattdessen über die Zugehörigkeit zur geladenen Schicht geprüft.
     */
    public ?int $optimizationId = null;

    /** @var array<int, string> Entwürfe je Vorschlag (editierbar). */
    public array $drafts = [];

    public bool $showFeedbackModal = false;

    public ?int $feedbackProposalId = null;

    public string $feedbackCategory = '';

    public ?string $feedbackComment = null;

    public bool $showRollbackModal = false;

    public string $rollbackReason = '';

    public ?string $cancellationMessage = null;

    public ?string $notice = null;

    /**
     * True, während ein asynchroner Live-Lauf in der Queue arbeitet; steuert
     * das Polling im View.
     */
    public bool $awaitingResult = false;

    /**
     * Nach einer Zuweisung: Direktlink zur ROI-Auswirkung (nur Reporting-Rollen).
     */
    public bool $showRoiLink = false;

    public function mount(int $shiftId): void
    {
        $shift = Shift::findOrFail($shiftId);

        Gate::authorize('update', $shift);

        $this->shiftId = $shiftId;
        $this->optimizationId = ShiftOptimization::where('shift_id', $shiftId)->latest()->value('id');
        $this->loadDrafts();
    }

    public function runOptimization(ShiftOptimizationRunner $runner): void
    {
        $shift = Shift::findOrFail($this->shiftId);

        Gate::authorize('update', $shift);

        $optimization = $runner->runOrQueue($shift);

        if ($optimization === null) {
            // Live-Treiber: Lauf läuft asynchron in der Queue (kein Request-Block).
            $this->awaitingResult = true;
            $this->notice = 'Live-Pipeline-Lauf gestartet – das Ergebnis erscheint in wenigen Sekunden.';

            return;
        }

        $this->optimizationId = $optimization->id;
        $this->awaitingResult = false;
        $this->loadDrafts();
        $this->notice = 'Pipeline-Lauf abgeschlossen ('.$optimization->driver_used->label().', '.$optimization->execution_time_ms.' ms).';
    }

    /**
     * Pollt das Ergebnis eines asynchronen (Live-)Laufs und blendet es ein,
     * sobald die Queue den Lauf abgeschlossen hat.
     */
    public function refreshOptimization(): void
    {
        $latestId = ShiftOptimization::where('shift_id', $this->shiftId)->latest('id')->value('id');

        if ($latestId === null || (int) $latestId === $this->optimizationId) {
            return;
        }

        $this->optimizationId = (int) $latestId;
        $this->awaitingResult = false;
        $this->loadDrafts();
        $this->notice = 'Live-Pipeline-Lauf abgeschlossen.';
    }

    public function assignProposal(int $proposalId, ShiftProposalAcceptService $acceptService): void
    {
        /** @var ShiftProposal $proposal */
        $proposal = $this->optimization()->proposals()->findOrFail($proposalId);

        /** @var Shift $shift */
        $shift = $proposal->optimization->shift;

        // Autorisierung VOR jeder Mutation (der Accept-Service prüft zusätzlich).
        Gate::authorize('update', $shift);

        // Editierter Benachrichtigungstext wird mit dem Vorschlag versioniert.
        if (array_key_exists($proposal->id, $this->drafts)) {
            $proposal->update(['draft_message' => $this->drafts[$proposal->id]]);
        }

        // Gemeinsamer Accept-Pfad: Autorisierung + Zuweisung + AiDecision-Audit.
        $acceptService->accept($shift, $proposal, actor: auth()->user());

        /** @var Employee|null $assigned */
        $assigned = $shift->fresh()?->assignedEmployee;

        $this->notice = ($assigned->name ?? $proposal->employee->name).' wurde zugewiesen (Ledger-Event geschrieben).';
        $this->showRoiLink = (bool) auth()->user()?->role->seesMetrics();
    }

    public function openFeedback(int $proposalId, string $rating): void
    {
        $proposal = $this->authorizedProposal($proposalId);

        if (FeedbackRating::tryFrom($rating) === FeedbackRating::Positive) {
            ShiftFeedback::create(['proposal_id' => $proposal->id, 'rating' => FeedbackRating::Positive]);
            $this->notice = 'Danke für das positive Feedback.';
            $this->loadDrafts();

            return;
        }

        $this->feedbackProposalId = $proposal->id;
        $this->feedbackCategory = '';
        $this->feedbackComment = null;
        $this->showFeedbackModal = true;
    }

    public function submitFeedback(): void
    {
        $this->validate([
            'feedbackProposalId' => ['required', 'integer'],
            'feedbackCategory' => ['required', 'string'],
            'feedbackComment' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'feedbackCategory' => 'Kategorie',
        ]);

        // Kein freies exists:shift_proposals,id (IDOR): der Vorschlag muss zum
        // geladenen Lauf dieser Schicht gehören und die Schicht autorisiert sein.
        $proposal = $this->authorizedProposal((int) $this->feedbackProposalId);

        ShiftFeedback::create([
            'proposal_id' => $proposal->id,
            'rating' => FeedbackRating::Negative,
            'reason_category' => $this->feedbackCategory,
            'comment' => $this->feedbackComment,
        ]);

        $this->showFeedbackModal = false;
        $this->notice = 'Negatives Feedback gespeichert – fließt in die KI-Qualitätskennzahlen ein.';
    }

    public function submitRollback(ShiftRollbackService $rollbacks): void
    {
        $this->validate([
            'rollbackReason' => ['required', 'string', 'max:500'],
            'cancellationMessage' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'rollbackReason' => 'Rückrollgrund',
        ]);

        $shift = Shift::findOrFail($this->shiftId);

        Gate::authorize('update', $shift);

        $rollbacks->rollback($shift, $this->rollbackReason, $this->cancellationMessage);

        $this->recordAiDecision(AiDecision::Rejected, $shift);

        $this->showRollbackModal = false;
        $this->rollbackReason = '';
        $this->cancellationMessage = null;
        $this->showRoiLink = false;
        $this->notice = 'Zuweisung als Forward-Event zurückgerollt – Schicht ist wieder offen.';
    }

    public function render(): View
    {
        $shift = Shift::with('assignedEmployee')->findOrFail($this->shiftId);
        $optimization = $this->loadOptimization();

        return view('livewire.shift-dispatch', [
            'shift' => $shift,
            'optimization' => $optimization,
            'auditEvents' => $shift->auditEvents()->latest('version')->limit(20)->get(),
        ]);
    }

    /**
     * Lädt den angezeigten Lauf nur, wenn er zur geladenen Schicht gehört.
     * Schützt vor manipuliertem optimizationId (Livewire-Property-Tampering).
     */
    private function loadOptimization(): ?ShiftOptimization
    {
        if ($this->optimizationId === null) {
            return null;
        }

        return ShiftOptimization::with(['proposals.employee', 'proposals.feedbacks'])
            ->where('shift_id', $this->shiftId)
            ->find($this->optimizationId);
    }

    private function optimization(): ShiftOptimization
    {
        $optimization = $this->loadOptimization();

        if ($optimization === null) {
            abort(404);
        }

        return $optimization;
    }

    /**
     * Vorschlag + zugehörige Schicht unter Autorisierung auflösen: schließt
     * IDOR über frei wählbare proposal_id (fremde Abteilung) aus.
     */
    private function authorizedProposal(int $proposalId): ShiftProposal
    {
        /** @var ShiftProposal $proposal */
        $proposal = $this->optimization()->proposals()->findOrFail($proposalId);

        /** @var Shift $shift */
        $shift = $proposal->optimization->shift;

        Gate::authorize('update', $shift);

        return $proposal;
    }

    /**
     * Schreibt eine AI-Entscheidung (akzeptiert/abgelehnt) in den Audit-Trail.
     * Das AiResult wird aus der aktuell geladenen ShiftOptimization rekonstruiert
     * (optimizationId statt newest-of-shift, damit parallele Läufe nicht die
     * falsche Konversation auditieren) – conversation_id und Antworttext liegen
     * im raw_response (T14-Ruling). Ohne Optimization bzw. conversation_id wird
     * trotzdem auditiert (conversationId = null), kein stiller Abbruch.
     */
    private function recordAiDecision(AiDecision $decision, Shift $shift): void
    {
        $optimization = $this->loadOptimization();

        $result = AiResult::fromRawResponse($optimization->raw_response ?? null);

        app(AiDecisionAuditor::class)->record($decision, $result, auditable: $shift, actor: auth()->user());
    }

    private function loadDrafts(): void
    {
        $this->drafts = [];

        if ($this->optimizationId === null) {
            return;
        }

        $optimization = $this->loadOptimization();

        if ($optimization === null) {
            return;
        }

        foreach ($optimization->proposals as $proposal) {
            $this->drafts[$proposal->id] = $proposal->draft_message ?? '';
        }
    }
}
