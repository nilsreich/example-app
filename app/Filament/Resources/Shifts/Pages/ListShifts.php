<?php

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('seedDemo')
                ->label('Demo-Szenario laden')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Demo-Szenario zurücksetzen?')
                ->modalDescription('Alle kiventro Demo-Daten (Mitarbeiter, Schichten, Läufe, Feedback, Ledger) werden gelöscht und neu erzeugt.')
                ->action(function (): void {
                    Artisan::call('db:seed-kiventro-demo');

                    Notification::make()
                        ->title('Demo-Szenario geladen')
                        ->body(trim(Artisan::output()))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
