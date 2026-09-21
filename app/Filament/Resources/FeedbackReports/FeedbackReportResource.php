<?php

namespace App\Filament\Resources\FeedbackReports;

use App\Enums\FeedbackStatus;
use App\Filament\Resources\FeedbackReports\Pages\ListFeedbackReports;
use App\Filament\Resources\FeedbackReports\Pages\ViewFeedbackReport;
use App\Filament\Resources\FeedbackReports\Schemas\FeedbackReportInfolist;
use App\Filament\Resources\FeedbackReports\Tables\FeedbackReportsTable;
use App\Models\FeedbackReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FeedbackReportResource extends Resource
{
    protected static ?string $model = FeedbackReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Feedback-Meldungen';

    protected static UnitEnum|string|null $navigationGroup = 'Betrieb';

    protected static ?string $modelLabel = 'Feedback-Meldung';

    protected static ?string $pluralModelLabel = 'Feedback-Meldungen';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'message';

    public static function infolist(Schema $schema): Schema
    {
        return FeedbackReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeedbackReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedbackReports::route('/'),
            'view' => ViewFeedbackReport::route('/{record}'),
        ];
    }

    /**
     * Eingangszähler: neue Meldungen direkt in der Navigation sichtbar.
     */
    public static function getNavigationBadge(): ?string
    {
        $new = static::getModel()::where('status', FeedbackStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Meldungen entstehen nur über das In-App-Widget (kein manuelles Anlegen).
     * Löschen regelt die Policy (nur admin).
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
