<?php

namespace App\Filament\Resources\Shifts\Schemas;

use App\Enums\ShiftStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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
                Select::make('status')
                    ->options(ShiftStatus::class)
                    ->default('open')
                    ->required(),
                Select::make('assigned_employee_id')
                    ->relationship('assignedEmployee', 'name'),
            ]);
    }
}
