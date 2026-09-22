<?php

namespace App\Feedback\Policies;

use App\Feedback\Models\FeedbackReport;
use App\Models\User;

/**
 * Triage-Zugriff nur für Nutzer mit Settings-Verwaltung (WebAdmin) —
 * und nur solange das feedback-Modul über die Config aktiviert ist.
 * Die Policy wird explizit im FeedbackServiceProvider gebunden
 * (Modul-Klasse statt Laravel-Konvention über den Namespace).
 */
class FeedbackReportPolicy
{
    public function viewAny(User $user): bool
    {
        return config('feedback.enabled', true) && $user->role->managesSettings();
    }

    public function view(User $user, FeedbackReport $feedbackReport): bool
    {
        return config('feedback.enabled', true) && $user->role->managesSettings();
    }

    public function create(User $user): bool
    {
        return config('feedback.enabled', true) && $user->role->managesSettings();
    }

    public function update(User $user, FeedbackReport $feedbackReport): bool
    {
        return config('feedback.enabled', true) && $user->role->managesSettings();
    }

    public function delete(User $user, FeedbackReport $feedbackReport): bool
    {
        return config('feedback.enabled', true) && $user->role->managesSettings();
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
