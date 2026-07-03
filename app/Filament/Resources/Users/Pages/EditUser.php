<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\AnonymizeUserService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
