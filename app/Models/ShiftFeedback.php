<?php

namespace App\Models;

use App\Enums\FeedbackRating;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Daumen-Feedback der Disposition zur Qualität eines KI-Vorschlags.
 * Fließt in Automatisierungsquote und Feedback-Widgets ein.
 *
 * @property int $id
 * @property int $proposal_id
 * @property FeedbackRating $rating
 * @property string|null $reason_category
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['proposal_id', 'rating', 'reason_category', 'comment'])]
class ShiftFeedback extends Model
{
    /**
     * "Feedback" ist unzählbar – Eloquent würde sonst "shift_feedback" raten.
     */
    protected $table = 'shift_feedbacks';

    /**
     * @return BelongsTo<ShiftProposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ShiftProposal::class, 'proposal_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => FeedbackRating::class,
        ];
    }
}
