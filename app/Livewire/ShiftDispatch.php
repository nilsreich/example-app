<?php

namespace App\Livewire;

use App\Enums\FeedbackRating;
use App\Models\Shift;
use App\Models\ShiftFeedback;
use App\Models\ShiftOptimization;
use App\Models\ShiftProposal;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftOptimizationRunner;
use App\Services\ShiftRollbackService;
use Illuminate\Contracts\View\View;
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

    public function mount(int $shiftId): void
    {
        $this->shiftId = $shiftId;
        $this->optimizationId = ShiftOptimization::where('shift_id', $shiftId)->latest()->value('id');
        $this->loadDrafts();
    }

    public function runOptimization(ShiftOptimizationRunner $runner): void
    {
        $optimization = $runner->run(Shift::findOrFail($this->shiftId));

        $this->optimizationId = $optimization->id;
        $this->loadDrafts();
        $this->notice = 'Pipeline-Lauf abgeschlossen ('.$optimization->driver_used->label().', '.$optimization->execution_time_ms.' ms).';
    }

    public function assignProposal(int $proposalId, ShiftAssignmentService $assignments): void
    {
        $proposal = $this->optimization()->proposals()->findOrFail($proposalId);

        // Editierter Benachrichtigungstext wird mit dem Vorschlag versioniert.
        if (array_key_exists($proposal->id, $this->drafts)) {
            $proposal->update(['draft_message' => $this->drafts[$proposal->id]]);
        }

        $assignments->assign($proposal->optimization->shift, $proposal->employee);

        $this->notice = $proposal->employee->name.' wurde zugewiesen (Ledger-Event geschrieben).';
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

        $rollbacks->rollback(Shift::findOrFail($this->shiftId), $this->rollbackReason, $this->cancellationMessage);

        $this->showRollbackModal = false;
        $this->rollbackReason = '';
        $this->cancellationMessage = null;
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
