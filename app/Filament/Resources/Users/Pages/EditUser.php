<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\AnonymizeUserService;
use App\Services\Auth\WelcomeInvitationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Email d'accueil (PRD §3.2) : renvoyable à tout moment, pas
            // seulement avant la première connexion — c'est aussi la seule
            // voie dont dispose l'admin pour débloquer un membre qui n'arrive
            // plus à se connecter, puisqu'il ne peut pas lui fixer de mot de
            // passe. Chaque envoi invalide le jeton précédent.
            Action::make('resendWelcome')
                ->label('Renvoyer l\'email d\'accueil')
                ->icon(Heroicon::OutlinedEnvelope)
                ->visible(fn (User $record): bool => (Auth::user()?->can('update', $record) ?? false)
                    && $record->anonymized_at === null)
                ->requiresConfirmation()
                ->modalHeading('Renvoyer l\'email d\'accueil ?')
                ->modalDescription('Le membre recevra un lien de définition de mot de passe valable 3 jours. Le lien envoyé précédemment cessera de fonctionner. Aucun mot de passe n\'est transmis.')
                ->modalSubmitActionLabel('Envoyer')
                ->action(function (User $record, WelcomeInvitationService $invitations): void {
                    if ($invitations->send($record)) {
                        Notification::make()
                            ->success()
                            ->title('Email d\'accueil envoyé')
                            ->send();

                        return;
                    }

                    // Chaque envoi périme le lien précédent : un second clic
                    // dans la minute condamnerait le lien qui vient de partir.
                    Notification::make()
                        ->warning()
                        ->title('Email d\'accueil déjà envoyé')
                        ->body('Un lien vient d\'être envoyé à ce membre. Patientez une minute avant d\'en générer un nouveau : le renvoi annulerait celui qu\'il a reçu.')
                        ->send();
                }),

            // Anonymisation RGPD (PRD §5.6, C12.7) : écrase la PII, révoque
            // les accès et soft-delete. Factures conservées (10 ans). Logique
            // dans AnonymizeUserService, visibilité via UserPolicy::anonymize.
            Action::make('anonymize')
                ->label('Anonymiser (RGPD)')
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->color('danger')
                ->visible(fn (User $record): bool => Auth::user()?->can('anonymize', $record) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Anonymiser cet utilisateur ?')
                ->modalDescription(
                    'Les données personnelles (identité, email, photo, profil) seront '
                    .'définitivement effacées et tous les accès révoqués. Les factures '
                    .'sont conservées (obligation fiscale, 10 ans). Action irréversible.',
                )
                ->modalSubmitActionLabel('Anonymiser définitivement')
                ->action(function (User $record): void {
                    /** @var User $actor */
                    $actor = Auth::user();
                    app(AnonymizeUserService::class)->anonymize($record, $actor);
                })
                ->successNotificationTitle('Utilisateur anonymisé')
                ->successRedirectUrl(fn (): string => static::getResource()::getUrl('index')),

            DeleteAction::make(),
            // Pas de ForceDeleteAction : UserPolicy::forceDelete() est `false`
            // en dur (RGPD : soft delete + anonymisation uniquement).
            RestoreAction::make(),
        ];
    }
}
