<?php

namespace App\Filament\Resources\Shifts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->required(),
                TextInput::make('department')
                    ->required(),
                TagsInput::make('required_qualifications')
                    ->label('Benötigte Qualifikationen')
                    ->suggestions(['Staplerschein', 'Ersthelfer', 'Kranführerschein', 'ADR-Schein', 'Schichtleitung'])
                    ->columnSpanFull(),
                // status und Zuweisung sind bewusst NICHT editierbar: Zustandsübergänge
                // laufen ausschließlich über die Services + Audit-Ledger (Domänen-Invariante).
            ]);
    }
}
