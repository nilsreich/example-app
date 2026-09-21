<?php

namespace App\Policies;

use App\Models\FeedbackReport;
use App\Models\User;

/**
 * Zugriffsregeln für In-App-Feedback: nur Panel-Rollen (admin/disponent)
 * dürfen Meldungen sehen und triagieren. Melder sehen ihre Meldung nicht
 * im Panel – Triage ist eine Betreiber-Aufgabe.
 */
class FeedbackReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isPanelRole($user);
    }

    public function view(User $user, FeedbackReport $feedbackReport): bool
    {
        return $this->isPanelRole($user);
    }

    public function create(User $user): bool
    {
        return $this->isPanelRole($user);
    }

    public function update(User $user, FeedbackReport $feedbackReport): bool
    {
        return $this->isPanelRole($user);
    }

    public function delete(User $user, FeedbackReport $feedbackReport): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, FeedbackReport $feedbackReport): bool
    {
        return false;
    }

    public function forceDelete(User $user, FeedbackReport $feedbackReport): bool
    {
        return false;
    }

    private function isPanelRole(User $user): bool
    {
        return in_array($user->role, ['admin', 'disponent'], true);
    }
}
