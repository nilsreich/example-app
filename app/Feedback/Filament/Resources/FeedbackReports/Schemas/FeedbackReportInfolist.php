<?php

namespace App\Feedback\Filament\Resources\FeedbackReports\Schemas;

use App\Feedback\Enums\FeedbackCategory;
use App\Feedback\Enums\FeedbackStatus;
use App\Feedback\Models\FeedbackReport;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FeedbackReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Meldung')
                    ->columns(2)
                    ->components([
                        TextEntry::make('category')
                            ->label('Kategorie')
                            ->badge()
                            ->formatStateUsing(fn (FeedbackCategory $state): string => $state->label())
                            ->color(fn (FeedbackCategory $state): string => $state->color()),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (FeedbackStatus $state): string => $state->label())
                            ->color(fn (FeedbackStatus $state): string => $state->color()),
                        TextEntry::make('message')
                            ->label('Beschreibung')
                            ->columnSpanFull(),
                        TextEntry::make('user.name')
                            ->label('Melder'),
                        TextEntry::make('created_at')
                            ->label('Eingegangen')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('resolution_note')
                            ->label('Lösungsnotiz')
                            ->placeholder('–')
                            ->columnSpanFull(),
                    ]),

                Section::make('Kontext (lokal erfasst)')
                    ->columns(2)
                    ->components([
                        TextEntry::make('page_title')
                            ->label('Seitentitel')
                            ->placeholder('–'),
                        TextEntry::make('page_url')
                            ->label('URL')
                            ->url(fn (FeedbackReport $record): string => $record->page_url, shouldOpenInNewTab: true)
                            ->limit(60),
                        TextEntry::make('element_selector')
                            ->label('Markiertes Element (CSS)')
                            ->fontFamily('mono')
                            ->placeholder('–'),
                        TextEntry::make('element_text')
                            ->label('Element-Text')
                            ->placeholder('–'),
                        KeyValueEntry::make('browser_info')
                            ->label('Browser')
                            ->columnSpanFull(),
                    ]),

                Section::make('Screenshot')
                    ->visible(fn (FeedbackReport $record): bool => $record->hasScreenshot())
                    ->components([
                        ImageEntry::make('screenshot_path')
                            ->hiddenLabel()
                            // Privater Disk: Auslieferung nur über policy-geschützte Route.
                            ->getStateUsing(fn (FeedbackReport $record): ?string => $record->screenshotUrl())
                            ->imageHeight(360),
                    ]),
            ]);
    }
}
