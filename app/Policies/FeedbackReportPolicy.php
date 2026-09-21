<?php

namespace App\Policies;

use App\Models\FeedbackReport;
use App\Models\User;

/**
 * Triage der In-App-Feedback-Meldungen ist Systemverwaltung:
 * nur der Web-Admin sieht den Eingang und bearbeitet ihn.
 * Feedback *geben* dürfen alle angemeldeten Nutzer (eigene Route).
 */
class FeedbackReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->managesSettings();
    }

    public function view(User $user, FeedbackReport $feedbackReport): bool
    {
        return $user->role->managesSettings();
    }

    public function create(User $user): bool
    {
        return $user->role->managesSettings();
    }

    public function update(User $user, FeedbackReport $feedbackReport): bool
    {
        return $user->role->managesSettings();
    }

    public function delete(User $user, FeedbackReport $feedbackReport): bool
    {
        return $user->role->managesSettings();
    }

    public function restore(User $user, FeedbackReport $feedbackReport): bool
    {
        return false;
    }

    public function forceDelete(User $user, FeedbackReport $feedbackReport): bool
    {
        return false;
    }
}
