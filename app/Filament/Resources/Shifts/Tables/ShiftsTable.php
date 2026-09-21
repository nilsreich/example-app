<?php

namespace App\Filament\Resources\Shifts\Tables;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\ShiftProposal;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftOptimizationRunner;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Schicht')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->label('Beginn')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ende')
                    ->dateTime('H:i')
                    ->sortable(),
                TextColumn::make('department')
                    ->label('Abteilung')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ShiftStatus $state): string => $state->label())
                    ->color(fn (ShiftStatus $state): string => match ($state) {
                        // Rot = unbesetzt (Handlungsbedarf im Dispatching).
                        ShiftStatus::Open => 'danger',
                        ShiftStatus::Assigned => 'success',
                        ShiftStatus::Cancelled => 'gray',
                    })
                    ->sortable(),
                // Entscheidung auf einen Blick: Top-Match direkt in der Zeile.
                TextColumn::make('ki_top_match')
                    ->label('KI-Top-Vorschlag')
                    ->badge()
                    ->state(fn (Shift $record): ?string => self::topMatchLabel($record))
                    ->color(fn (Shift $record): string => self::topMatchColor($record))
                    ->placeholder(fn (Shift $record): string => $record->status === ShiftStatus::Open
                        ? 'Noch nicht berechnet'
                        : '–'),
                TextColumn::make('assignedEmployee.name')
                    ->label('Besetzt mit')
                    ->placeholder('–')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ShiftStatus::class),
                SelectFilter::make('department')
                    ->label('Abteilung')
                    ->options([
                        'Logistik' => 'Logistik',
                        'Produktion' => 'Produktion',
                        'Versand' => 'Versand',
                    ]),
            ])
            ->emptyStateHeading('Keine Schichten gefunden')
            ->emptyStateDescription('Filter anpassen oder das Demo-Szenario laden, um sofort Daten zu sehen.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->recordActions([
                // 1-Klick-Übernahme des Top-Vorschlags – mit kurzer Bestätigung.
                Action::make('accept')
                    ->label('Akzeptieren')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Shift $record): bool => self::canAccept($record))
                    ->requiresConfirmation()
                    ->modalHeading('Top-Vorschlag übernehmen?')
                    ->modalDescription(fn (Shift $record): string => sprintf(
                        '%s (%d %%) wird der Schicht "%s" verbindlich zugewiesen. Die Änderung wird revisionssicher im Forward-Ledger protokolliert.',
                        self::topProposal($record)?->employee->name ?? '–',
                        self::topProposal($record)?->score ?? 0,
                        $record->title,
                    ))
                    ->modalSubmitActionLabel('Zuweisen')
                    ->action(function (Shift $record): void {
                        $proposal = self::topProposal($record);

                        if ($proposal === null) {
                            return;
                        }

                        app(ShiftAssignmentService::class)->assign($record, $proposal->employee);

                        Notification::make()
                            ->title($proposal->employee->name.' wurde zugewiesen')
                            ->body('Ledger-Event geschrieben. Auswirkung im ROI-Dashboard sichtbar.')
                            ->success()
                            ->send();
                    }),

                // Ohne Lauf: Vorschläge direkt in der Zeile berechnen.
                Action::make('compute')
                    ->label('Berechnen')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->visible(fn (Shift $record): bool => $record->status === ShiftStatus::Open
                        && $record->latestOptimization === null
                        && self::canDispatch($record))
                    ->action(function (Shift $record): void {
                        $optimization = app(ShiftOptimizationRunner::class)->run($record);
                        $top = $optimization->proposals->first();

                        Notification::make()
                            ->title('Vorschläge berechnet')
                            ->body($top
                                ? "Top-Match: {$top->employee->name} ({$top->score} %)"
                                : 'Keine passenden Kandidaten gefunden.')
                            ->success()
                            ->send();
                    }),

                // Alle Details: Slide-Over mit Vorschlägen, Feedback und Rollback.
                Action::make('dispatch')
                    ->label('Mehr')
                    ->icon('heroicon-o-bars-3')
                    // Dispatching nur für Rollen mit Dispositionsrecht im eigenen Scope.
                    ->visible(fn (Shift $record): bool => self::canDispatch($record))
                    ->slideOver()
                    ->modalHeading(fn (Shift $record): string => 'Dispatching: '.$record->title)
                    ->modalWidth(Width::ExtraLarge)
                    ->modalContent(fn (Shift $record) => view('filament.shifts.dispatch-modal', ['shiftId' => $record->id]))
                    // Reine Anzeige-/Arbeitsfläche (Livewire trägt die Aktionen).
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function canDispatch(Shift $record): bool
    {
        return (bool) (auth()->user()?->can('update', $record));
    }

    private static function canAccept(Shift $record): bool
    {
        return $record->status === ShiftStatus::Open
            && self::topProposal($record) !== null
            && self::canDispatch($record);
    }

    private static function topProposal(Shift $record): ?ShiftProposal
    {
        return $record->latestOptimization?->topProposal;
    }

    /**
     * "Name · Score %" – nur für offene Schichten mit berechnetem Lauf.
     */
    private static function topMatchLabel(Shift $record): ?string
    {
        if ($record->status !== ShiftStatus::Open) {
            return null;
        }

        $proposal = self::topProposal($record);

        return $proposal === null
            ? null
            : $proposal->employee->name.' · '.$proposal->score.' %';
    }

    /**
     * Konfidenz-Farbgebung analog zum Slide-Over (grün ≥ 90, gelb ≥ 70, sonst rot).
     */
    private static function topMatchColor(Shift $record): string
    {
        $score = self::topProposal($record)?->score;

        return match (true) {
            $score === null => 'gray',
            $score >= 90 => 'success',
            $score >= 70 => 'warning',
            default => 'danger',
        };
    }
}
