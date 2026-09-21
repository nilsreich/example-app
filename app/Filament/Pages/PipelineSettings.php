<?php

namespace App\Filament\Pages;

use App\Enums\PipelineDriver;
use App\Models\Setting;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PipelineSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $navigationLabel = 'KI-Pipeline';

    protected static UnitEnum|string|null $navigationGroup = 'Einstellungen';

    protected static ?string $title = 'KI-Pipeline-Modus';

    protected string $view = 'filament.pages.pipeline-settings';

    /**
     * Nur der Web-Admin steuert die KI-Pipeline (Systemeinstellung).
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->role->managesSettings() ?? false;
    }

    public static function getDriverOptions(): array
    {
        return [
            PipelineDriver::Mock->value => PipelineDriver::Mock->label().' – deterministisch, ohne API-Key',
            PipelineDriver::Live->value => PipelineDriver::Live->label().' – echtes AI SDK (Provider-Key nötig)',
        ];
    }

    public static function currentDriver(): PipelineDriver
    {
        return Setting::aiPipelineDriver();
    }
}
