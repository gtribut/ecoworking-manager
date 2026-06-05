<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

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
                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->rule('min:8')
                            // Requis à la création ; à l'édition, vide = inchangé.
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->helperText('Laisser vide à l\'édition pour conserver le mot de passe actuel.'),
                        Select::make('roles')
                            ->label('Rôles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            // Libellés FR via l'enum Role (la valeur stockée = nom Spatie).
                            ->options(collect(Role::cases())->mapWithKeys(
                                fn (Role $role): array => [$role->value => $role->getLabel()],
                            ))
                            ->helperText('Rôles d\'usage (résident/additionnel/externe/équipe) exclusifs entre eux ; admin et contact facturation se cumulent.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
