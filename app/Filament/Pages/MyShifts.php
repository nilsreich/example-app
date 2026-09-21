<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class MyShifts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Meine Schichten';

    protected static UnitEnum|string|null $navigationGroup = 'Mein Bereich';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Meine Schichten';

    protected string $view = 'filament.pages.my-shifts';

    /**
     * Self-Service-Seite: Mitarbeiter und (zur Kontrolle) Web-Admin.
     */
    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, [UserRole::Nutzer, UserRole::WebAdmin], true);
    }
}
