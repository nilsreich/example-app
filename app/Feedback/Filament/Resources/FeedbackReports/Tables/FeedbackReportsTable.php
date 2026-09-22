<?php

namespace App\Feedback\Filament\Resources\FeedbackReports\Tables;

use App\Feedback\Enums\FeedbackCategory;
use App\Feedback\Enums\FeedbackStatus;
use App\Feedback\Models\FeedbackReport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeedbackReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('category')
                    ->label('Kategorie')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackCategory $state): string => $state->label())
                    ->color(fn (FeedbackCategory $state): string => $state->color()),
                TextColumn::make('message')
                    ->label('Meldung')
                    ->limit(70)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Melder')
                    ->searchable(),
                TextColumn::make('page_url')
                    ->label('Seite')
                    ->limit(40)
                    ->url(fn (FeedbackReport $record): string => $record->page_url, shouldOpenInNewTab: true)
                    ->icon('heroicon-o-arrow-top-right-on-square'),
                IconColumn::make('screenshot_path')
                    ->label('Screenshot')
                    ->boolean()
                    ->trueIcon('heroicon-o-camera')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackStatus $state): string => $state->label())
                    ->color(fn (FeedbackStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(FeedbackStatus::class),
                SelectFilter::make('category')
                    ->label('Kategorie')
                    ->options(FeedbackCategory::class),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('start')
                    ->label('In Arbeit')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->visible(fn (FeedbackReport $record): bool => $record->status === FeedbackStatus::New)
                    ->action(fn (FeedbackReport $record): mixed => $record->transitionTo(FeedbackStatus::InProgress, actor: auth()->user())),
                Action::make('resolve')
                    ->label('Erledigt')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (FeedbackReport $record): bool => $record->status !== FeedbackStatus::Resolved)
                    ->form([
                        Textarea::make('resolution_note')
                            ->label('Lösungsnotiz (optional)')
                            ->maxLength(2000),
                    ])
                    ->fillForm(fn (FeedbackReport $record): array => ['resolution_note' => $record->resolution_note])
                    ->action(function (FeedbackReport $record, array $data): void {
                        $record->transitionTo(FeedbackStatus::Resolved, $data['resolution_note'] ?? null, actor: auth()->user());

                        Notification::make()->title('Meldung als erledigt markiert')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
