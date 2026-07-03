<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLog\Schemas;

use App\Filament\Resources\ActivityLog\ActivityResource;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Détail d'une entrée d'audit (PRD §4.14) : contexte de l'événement + diff
 * avant/après lisible (colonne `attribute_changes` — PAS `properties`,
 * piège connu activitylog v5).
 */
class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Événement')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('causer_id')
                            ->label('Par')
                            ->formatStateUsing(fn (Activity $record): string => $record->causer instanceof User
                                ? "{$record->causer->fullName()} ({$record->causer->email})"
                                : 'Système')
                            ->placeholder('Système'),
                        TextEntry::make('event')
                            ->label('Action')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'created' => 'Création',
                                'updated' => 'Modification',
                                'deleted' => 'Suppression',
                                'restored' => 'Restauration',
                                default => $state ?? '—',
                            })
                            ->color(fn (?string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('subject_id')
                            ->label('Sujet')
                            ->formatStateUsing(fn (Activity $record): string => ActivityResource::subjectDisplay($record)),
                    ]),
                Section::make('Changements')
                    ->schema([
                        ViewEntry::make('attribute_changes')
                            ->hiddenLabel()
                            ->view('filament.infolists.activity-diff'),
                    ]),
            ]);
    }
}
