<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\InternalDocument;
use App\Services\InternalDocumentPublicationService;

/**
 * Cycle de vie d'un document commun (lot G, PRD §3.8.4). Notifie l'audience
 * quand le document devient lisible par les membres :
 *
 *  - création déjà publiée ;
 *  - passage à l'état publié (`published_at` renseignée ou réactivation) ;
 *  - **nouvelle version** d'un document publié → re-validation requise
 *    (data_model §4.5), donc nouvelle notification.
 *
 * Observer plutôt que hook Filament : la notification part quel que soit le
 * chemin d'écriture (Resource admin, seeder, artisan), comme pour les annonces.
 * Pas de garde sur l'acteur : publier un document commun est par construction
 * un acte d'administration, jamais l'action du destinataire.
 */
final class InternalDocumentObserver
{
    /** Attributs dont le changement (re)déclenche une diffusion. */
    private const array PUBLICATION_ATTRIBUTES = ['version', 'published_at', 'is_active'];

    public function __construct(private readonly InternalDocumentPublicationService $publication) {}

    public function created(InternalDocument $document): void
    {
        if ($document->isPublished()) {
            $this->publication->notifyAudience($document);
        }
    }

    public function updated(InternalDocument $document): void
    {
        if ($document->wasChanged(self::PUBLICATION_ATTRIBUTES) && $document->isPublished()) {
            $this->publication->notifyAudience($document);
        }
    }
}
