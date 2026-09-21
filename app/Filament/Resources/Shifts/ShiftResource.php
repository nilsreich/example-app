<?php

namespace App\Filament\Resources\Shifts;

use App\Enums\ShiftStatus;
use App\Filament\Resources\Shifts\Pages\CreateShift;
use App\Filament\Resources\Shifts\Pages\EditShift;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Filament\Resources\Shifts\Schemas\ShiftForm;
use App\Filament\Resources\Shifts\Tables\ShiftsTable;
use App\Models\Shift;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Schichten';

    protected static UnitEnum|string|null $navigationGroup = 'Dispatching';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Schicht';

    protected static ?string $pluralModelLabel = 'Schichten';

    public static function form(Schema $schema): Schema
    {
        return ShiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShiftsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Abteilungs-Scope: Bereichsleiter sehen ausschließlich ihre Abteilung.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $departments = auth()->user()?->visibleDepartments();

        return $departments === null ? $query : $query->whereIn('department', $departments);
    }

    /**
     * Navigations-Badge: offene (unbesetzte) Schichten im eigenen Scope.
     */
    public static function getNavigationBadge(): ?string
    {
        $open = static::getEloquentQuery()->where('status', ShiftStatus::Open)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
            'create' => CreateShift::route('/create'),
            'edit' => EditShift::route('/{record}/edit'),
        ];
    }
}
