<?php

namespace App\Filament\Resources\Shifts\Tables;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
            ->recordActions([
                Action::make('dispatch')
                    ->label('KI-Ersatzvorschläge')
                    ->icon('heroicon-o-sparkles')
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
}
