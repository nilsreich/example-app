<?php

namespace App\Livewire;

use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDecision;
use App\Ai\Enums\AiDriver;
use App\Ai\Services\AiDecisionAuditor;
use App\Enums\FeedbackRating;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftFeedback;
use App\Models\ShiftOptimization;
use App\Models\ShiftProposal;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftOptimizationRunner;
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
     * Nach einer Zuweisung: Direktlink zur ROI-Auswirkung (nur Reporting-Rollen).
     */
    public bool $showRoiLink = false;

    public function mount(int $shiftId): void
    {
        $this->shiftId = $shiftId;
        $this->optimizationId = ShiftOptimization::where('shift_id', $shiftId)->latest()->value('id');
        $this->loadDrafts();
    }

    public function runOptimization(ShiftOptimizationRunner $runner): void
    {
        $shift = Shift::findOrFail($this->shiftId);

        Gate::authorize('update', $shift);

        $optimization = $runner->run($shift);

        $this->optimizationId = $optimization->id;
        $this->loadDrafts();
        $this->notice = 'Pipeline-Lauf abgeschlossen ('.$optimization->driver_used->label().', '.$optimization->execution_time_ms.' ms).';
    }

    public function assignProposal(int $proposalId, ShiftAssignmentService $assignments): void
    {
        /** @var ShiftProposal $proposal */
        $proposal = $this->optimization()->proposals()->findOrFail($proposalId);

        /** @var Shift $shift */
        $shift = $proposal->optimization->shift;

        // Rechtematrix: Nur Dispositionsrollen im eigenen Abteilungs-Scope.
        Gate::authorize('update', $shift);

        // Editierter Benachrichtigungstext wird mit dem Vorschlag versioniert.
        if (array_key_exists($proposal->id, $this->drafts)) {
            $proposal->update(['draft_message' => $this->drafts[$proposal->id]]);
        }

        /** @var Employee $employee */
        $employee = $proposal->employee;

        $assignments->assign($shift, $employee);

        $this->recordAiDecision(AiDecision::Accepted, $shift);

        $this->notice = $employee->name.' wurde zugewiesen (Ledger-Event geschrieben).';
        $this->showRoiLink = (bool) auth()->user()?->role->seesMetrics();
    }

    public function openFeedback(int $proposalId, string $rating): void
    {
        if ($rating === FeedbackRating::Positive->value) {
            ShiftFeedback::create(['proposal_id' => $proposalId, 'rating' => FeedbackRating::Positive]);
            $this->notice = 'Danke für das positive Feedback.';
            $this->loadDrafts();

            return;
        }

        $this->feedbackProposalId = $proposalId;
        $this->feedbackCategory = '';
        $this->feedbackComment = null;
        $this->showFeedbackModal = true;
    }

    public function submitFeedback(): void
    {
        $this->validate([
            'feedbackProposalId' => ['required', 'integer', 'exists:shift_proposals,id'],
            'feedbackCategory' => ['required', 'string'],
            'feedbackComment' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'feedbackCategory' => 'Kategorie',
        ]);

        ShiftFeedback::create([
            'proposal_id' => $this->feedbackProposalId,
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
        $optimization = $this->optimizationId
            ? ShiftOptimization::with(['proposals.employee', 'proposals.feedbacks'])->find($this->optimizationId)
            : null;

        return view('livewire.shift-dispatch', [
            'shift' => $shift,
            'optimization' => $optimization,
            'auditEvents' => $shift->auditEvents()->latest('version')->limit(20)->get(),
        ]);
    }

    private function optimization(): ShiftOptimization
    {
        return ShiftOptimization::findOrFail($this->optimizationId);
    }

    /**
     * Schreibt eine AI-Entscheidung (akzeptiert/abgelehnt) in den Audit-Trail.
     * Das AiResult wird aus der letzten ShiftOptimization rekonstruiert –
     * conversation_id und Antworttext liegen im raw_response (T14-Ruling).
     */
    private function recordAiDecision(AiDecision $decision, Shift $shift): void
    {
        $optimization = ShiftOptimization::query()
            ->where('shift_id', $shift->id)
            ->latest('id')
            ->first();

        $raw = $optimization->raw_response ?? [];
        $conversationId = $raw['conversation_id'] ?? null;

        if (! is_string($conversationId)) {
            return;
        }

        $result = new AiResult(
            agent: (string) ($raw['agent'] ?? 'shift-optimizer'),
            driver: AiDriver::tryFrom((string) ($raw['driver'] ?? 'mock')) ?? AiDriver::Mock,
            text: (string) ($raw['text'] ?? ''),
            conversationId: $conversationId,
        );

        app(AiDecisionAuditor::class)->record($decision, $result, auditable: $shift, actor: auth()->user());
    }

    private function loadDrafts(): void
    {
        $this->drafts = [];

        if (! $this->optimizationId) {
            return;
        }

        foreach (ShiftProposal::where('optimization_id', $this->optimizationId)->get() as $proposal) {
            $this->drafts[$proposal->id] = $proposal->draft_message ?? '';
        }
    }
}
