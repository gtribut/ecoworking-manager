<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\Contacts\Schemas\ContactForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contacts';

    public function form(Schema $schema): Schema
    {
        // Réutilise le schéma de la Resource Contact (DRY). Le champ `company_id`
        // y figure mais est ignoré ici : la relation impose déjà l'entité parente.
        return ContactForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('last_name')
            ->columns([
                TextColumn::make('last_name')
                    ->label('Contact')
                    ->formatStateUsing(fn ($record): string => trim("{$record->first_name} {$record->last_name}"))
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge(),
                IconColumn::make('is_primary')
                    ->label('Principal')
                    ->boolean(),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
