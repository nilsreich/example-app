<?php

namespace App\Models;

use App\Enums\PipelineDriver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Globaler Key-Value-Speicher (u. a. Pipeline-Modus "ai_pipeline_mode").
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const AI_PIPELINE_MODE = 'ai_pipeline_mode';

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): self
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value])->fresh();
    }

    /**
     * Aktiver Pipeline-Treiber; unbekannte/fehlende Werte fallen auf Mock zurück
     * (Demo bleibt ohne API-Key einsatzbereit).
     */
    public static function aiPipelineDriver(): PipelineDriver
    {
        return PipelineDriver::tryFrom((string) static::get(self::AI_PIPELINE_MODE, PipelineDriver::Mock->value))
            ?? PipelineDriver::Mock;
    }
}
