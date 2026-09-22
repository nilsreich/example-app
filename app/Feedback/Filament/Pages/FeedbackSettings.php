<?php

namespace App\Feedback\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class FeedbackSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'In-App-Feedback';

    protected static UnitEnum|string|null $navigationGroup = 'Einstellungen';

    protected static ?string $title = 'In-App-Feedback';

    protected string $view = 'filament.pages.feedback-settings';

    /**
     * Nur der Web-Admin schaltet das Feedback-Widget global — und nur,
     * solange das feedback-Modul konfigurativ aktiviert ist.
     */
    public static function canAccess(): bool
    {
        return config('feedback.enabled', true)
            && (auth()->user()?->role->managesSettings() ?? false);
    }

    public static function isEnabled(): bool
    {
        return Setting::feedbackWidgetEnabled();
    }
}
