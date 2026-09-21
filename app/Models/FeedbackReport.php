<?php

namespace App\Models;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * In-App-Feedback (Marker.io-Stil, aber lokal): Meldung mit Seitenkontext,
 * optionalem privatem Screenshot und Triage-Status.
 *
 * @property int $id
 * @property int $user_id
 * @property FeedbackCategory $category
 * @property string $message
 * @property string $page_url
 * @property string|null $page_title
 * @property string|null $element_selector
 * @property string|null $element_text
 * @property array<string, mixed>|null $browser_info
 * @property string|null $screenshot_path
 * @property FeedbackStatus $status
 * @property string|null $resolution_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'category', 'message', 'page_url', 'page_title', 'element_selector', 'element_text', 'browser_info', 'screenshot_path', 'status', 'resolution_note'])]
class FeedbackReport extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FeedbackCategory::class,
            'status' => FeedbackStatus::class,
            'browser_info' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasScreenshot(): bool
    {
        return $this->screenshot_path !== null;
    }

    /**
     * URL des privaten Screenshots (über Policy-geschützte Route,
     * nie direkt aus storage/app/private ausgeliefert).
     */
    public function screenshotUrl(): ?string
    {
        return $this->hasScreenshot() ? route('feedback.screenshot', $this) : null;
    }

    /**
     * Noch nicht erledigte Meldungen (Triage-Eingang).
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where('status', '!=', FeedbackStatus::Resolved);
    }

    /**
     * Screenshot-Inhalt für die Auslieferungs-Route.
     */
    public function screenshotContents(): ?string
    {
        if (! $this->hasScreenshot()) {
            return null;
        }

        return Storage::disk('local')->get($this->screenshot_path);
    }
}
