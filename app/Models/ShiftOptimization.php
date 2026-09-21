<?php

namespace App\Models;

use App\Enums\PipelineDriver;
use Database\Factories\ShiftOptimizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Protokollierter Pipeline-Lauf (Audit Trail): Welcher Treiber hat wann
 * mit welchem Kontext welche Antwort in welcher Zeit/Kosten erzeugt?
 *
 * @property int $id
 * @property int $shift_id
 * @property PipelineDriver $driver_used
 * @property array<string, mixed> $prompt_payload
 * @property array<string, mixed>|null $raw_response
 * @property int|null $execution_time_ms
 * @property int|null $tokens_used
 * @property string|null $cost_estimate_eur
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['shift_id', 'driver_used', 'prompt_payload', 'raw_response', 'execution_time_ms', 'tokens_used', 'cost_estimate_eur'])]
class ShiftOptimization extends Model
{
    /** @use HasFactory<ShiftOptimizationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver_used' => PipelineDriver::class,
            'prompt_payload' => 'array',
            'raw_response' => 'array',
            'cost_estimate_eur' => 'decimal:6',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Kandidaten-Vorschläge dieses Laufs, beste zuerst.
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(ShiftProposal::class, 'optimization_id')->orderByDesc('score');
    }
}
