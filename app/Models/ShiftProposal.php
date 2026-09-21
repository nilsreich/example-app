<?php

namespace App\Models;

use Database\Factories\ShiftProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Einzelner KI-Kandidatenvorschlag mit Score, Begründung und Trade-offs.
 *
 * @property int $id
 * @property int $optimization_id
 * @property int $employee_id
 * @property int $score
 * @property array<int, string>|null $match_reasons
 * @property array<int, string>|null $risks_or_tradeoffs
 * @property string|null $draft_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['optimization_id', 'employee_id', 'score', 'match_reasons', 'risks_or_tradeoffs', 'draft_message'])]
class ShiftProposal extends Model
{
    /** @use HasFactory<ShiftProposalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_reasons' => 'array',
            'risks_or_tradeoffs' => 'array',
        ];
    }

    public function optimization(): BelongsTo
    {
        return $this->belongsTo(ShiftOptimization::class, 'optimization_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(ShiftFeedback::class, 'proposal_id')->latest();
    }

    /**
     * Konfidenz-Stufe für die Badge-Farbgebung im Dispatching-UI:
     * high (90+), medium (70-89), low (<70).
     */
    public function confidenceTier(): string
    {
        return match (true) {
            $this->score >= 90 => 'high',
            $this->score >= 70 => 'medium',
            default => 'low',
        };
    }
}
