<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Auth\WelcomeInvitationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

/**
 * Création d'un compte membre par l'admin (PRD §3.2 — pas d'inscription
 * self-service). L'admin ne saisit AUCUN mot de passe : un secret aléatoire
 * jamais communiqué est posé (le compte n'est donc pas ouvert sans jeton),
 * puis le membre reçoit l'email d'accueil qui lui permet de définir le sien.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Cast `hashed` sur le modèle : la valeur est hashée à l'écriture.
        // Jamais journalisée, jamais affichée, jamais envoyée par mail.
        $data['password'] = Str::random(64);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        app(WelcomeInvitationService::class)->send($user);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Compte créé — email d\'accueil envoyé';
    }
}
