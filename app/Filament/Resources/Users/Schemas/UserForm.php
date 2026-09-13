<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Rules\ExclusiveUsageRole;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Prénom')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('last_name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ]),

                Section::make('Accès')
                    ->columns(2)
                    ->schema([
                        // Pas de champ mot de passe (PRD §3.2) : l'admin ne
                        // choisit ni ne connaît jamais le mot de passe d'un
                        // membre. À la création, un secret aléatoire est posé
                        // puis le membre reçoit un email d'accueil avec un lien
                        // de définition (CreateUser + action « Renvoyer l'email
                        // d'accueil » sur la fiche).
                        Placeholder::make('password_state')
                            ->label('Mot de passe')
                            ->content(fn (string $operation): string => $operation === 'create'
                                ? 'Le membre le définira lui-même via l\'email d\'accueil envoyé à la création.'
                                : 'Défini par le membre. Utilisez « Renvoyer l\'email d\'accueil » pour lui permettre de le redéfinir.'),
                        Select::make('roles')
                            ->label('Rôles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            // Libellés FR via l'enum Role. L'état du Select reste les IDs
                            // spatie (requis par la relation) — surcharger `options()` avec
                            // les noms cassait la sélection ET la sauvegarde (sync par ID).
                            ->getOptionLabelFromRecordUsing(
                                fn ($record): string => Role::tryFrom($record->name)?->getLabel() ?? $record->name,
                            )
                            // XOR des rôles d'usage (PRD §2.4) : au plus un parmi
                            // resident/additional/external/staff.
                            ->rules([new ExclusiveUsageRole])
                            ->helperText('Rôles d\'usage (résident/additionnel/externe/équipe) exclusifs entre eux ; admin et contact facturation se cumulent.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
