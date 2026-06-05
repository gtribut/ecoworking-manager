<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\MemberProfiles\Schemas\MemberProfileForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MemberProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'memberProfiles';

    protected static ?string $title = 'Membres';

    public function form(Schema $schema): Schema
    {
        // Réutilise le schéma de la Resource MemberProfile (DRY).
        return MemberProfileForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('user.last_name')
                    ->label('Membre')
                    ->formatStateUsing(fn ($record): string => $record->user?->fullName() ?? '—')
                    ->searchable(['users.first_name', 'users.last_name']),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('job_title')
                    ->label('Poste')
                    ->placeholder('—'),
                TextColumn::make('desk.name')
                    ->label('Bureau')
                    ->placeholder('—'),
                IconColumn::make('show_in_directory')
                    ->label('Annuaire')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
