<?php

namespace App\Audit\Filament\Pages;

use App\Audit\Enums\AuditEventType;
use App\Audit\Enums\AuditSource;
use App\Audit\Models\AuditEvent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only-Ansicht des Audit-Protokolls mit GoBD-Export (CSV/JSON).
 * Zugriff folgt dem Gate `audit.export` (Web-Admin, Geschäftsführung).
 */
final class AuditLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected string $view = 'filament.pages.audit-log';

    public static function canAccess(): bool
    {
        return Gate::allows('audit.export');
    }

    public function getTitle(): string
    {
        return 'Audit-Protokoll';
    }

    public function getHeading(): string
    {
        return 'Audit-Protokoll';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'audit-log';
    }

    public function table(Table $table): Table
    {
        $eventTypes = collect(AuditEventType::cases())
            ->mapWithKeys(fn (AuditEventType $type): array => [$type->value => $type->label()])
            ->all();

        return $table
            ->query(AuditEvent::query()->with('actor'))
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('created_at')->label('Zeitpunkt')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('event_type')
                    ->label('Ereignis')
                    ->formatStateUsing(fn (AuditEventType $state): string => $state->label())
                    ->badge()
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Quelle')
                    ->formatStateUsing(fn (?AuditSource $state): string => $state->value ?? 'web')
                    ->sortable(),
                TextColumn::make('actor.name')->label('Akteur')->placeholder('System'),
                TextColumn::make('auditable_type')
                    ->label('Objekt')
                    ->formatStateUsing(static fn (string $state): string => class_basename($state)),
                TextColumn::make('auditable_id')->label('Objekt-ID')->toggleable(),
                TextColumn::make('version')->label('Version')->sortable()->toggleable(),
                TextColumn::make('new_state')
                    ->label('Neuer Zustand')
                    ->formatStateUsing(static fn (mixed $state): string => (string) json_encode($state ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hash')
                    ->label('Hash')
                    ->copyable()
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Ereignis')
                    ->options($eventTypes)
                    ->placeholder('Alle Ereignisse'),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('CSV exportieren')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => route('audit.export', ['format' => 'csv']))
                ->openUrlInNewTab(),
            Action::make('exportJson')
                ->label('JSON exportieren')
                ->icon('heroicon-o-code-bracket')
                ->color('gray')
                ->url(fn (): string => route('audit.export', ['format' => 'json']))
                ->openUrlInNewTab(),
        ];
    }
}
