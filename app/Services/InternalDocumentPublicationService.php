<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InternalDocument;
use App\Models\User;
use App\Notifications\InternalDocumentPublishedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Diffusion d'un document interne à son audience (PRD §3.8.4, « nouveau
 * document interne à valider »). Le *quand* appartient à l'observer
 * (publication, nouvelle version) ; le *qui* et le *comment* vivent ici —
 * testable et réutilisable hors HTTP (CLAUDE.md §7).
 */
final class InternalDocumentPublicationService
{
    /**
     * Taille des lots d'envoi. Au-delà, `Notification::send` chargerait tous
     * les destinataires en mémoire d'un coup (≈ 125 comptes aujourd'hui, mais
     * la requête n'est pas bornée par nature).
     */
    private const int CHUNK = 100;

    /** Notifie tous les membres couverts par l'audience du document. */
    public function notifyAudience(InternalDocument $document): void
    {
        $this->recipients($document)->chunkById(
            self::CHUNK,
            function (Collection $recipients) use ($document): void {
                Notification::send($recipients, new InternalDocumentPublishedNotification($document));
            },
        );
    }

    /**
     * Destinataires = membres dont un rôle est couvert par l'audience
     * (`Audience::roles()`, même règle que le scope de lecture portail
     * `InternalDocument::applicableTo`), hors auteur du document.
     *
     * Exclusions RGPD (CLAUDE.md §3.4) : les comptes anonymisés et les comptes
     * désactivés (soft delete, écartés par le global scope de `User`) ne
     * reçoivent plus rien.
     *
     * @return Builder<User>
     */
    private function recipients(InternalDocument $document): Builder
    {
        return User::query()
            ->whereNull('anonymized_at')
            // whereHas plutôt que le scope spatie `role()` : ce dernier lève
            // RoleDoesNotExist si un rôle n'a pas encore été seedé.
            ->whereHas('roles', function ($query) use ($document): void {
                $query->whereIn('name', $document->audience->roleValues());
            })
            ->when($document->created_by !== null, fn (Builder $query) => $query->whereKeyNot($document->created_by));
    }
}
