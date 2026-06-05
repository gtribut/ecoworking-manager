<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Tables;

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\Audience;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->weight('medium')
                    ->limit(40),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('visibility')
                    ->label('Visibilité')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('event_starts_at')
                    ->label('Événement')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('requires_registration')
                    ->label('Inscription')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->label('Publiée le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(AnnouncementType::class),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(AnnouncementStatus::class),
                SelectFilter::make('visibility')
                    ->label('Visibilité')
                    ->options(Audience::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
