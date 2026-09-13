<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\InternalDocument;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Nouveau document interne à valider (PRD §3.8.4) — événement **critique**,
 * donc doublé par email quand le membre n'a pas coupé le toggle. Émis à la
 * publication d'un document et à chaque nouvelle version (re-validation
 * requise, data_model §4.5) ; le ciblage par audience est fait en amont
 * (InternalDocumentPublicationService).
 *
 * Titre et version figés À LA CONSTRUCTION plutôt que portés en modèle (review) :
 * `ShouldQueue` + `SerializesModels` ne sérialise qu'un identifiant, pas les
 * attributs — deux publications rapprochées (v1 puis v2) tant que le job de la
 * première est encore en queue faisaient relire le document DEPUIS LA DB au
 * traitement, donc la v2 dans les DEUX notifications. Même piège documenté sur
 * `AbsenceRecordedNotification`.
 *
 * Aucune PII dans la charge utile : seuls le titre et la version du document.
 */
final class InternalDocumentPublishedNotification extends PortalNotification
{
    private readonly int $documentId;

    private readonly string $title;

    private readonly string $version;

    public function __construct(InternalDocument $document)
    {
        $this->documentId = $document->id;
        $this->title = $document->title;
        $this->version = $document->version;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nouveau document à valider : {$this->title}")
            ->greeting('Bonjour,')
            ->line("Le document « {$this->title} » (version {$this->version}) est à lire et à valider dans votre espace membre.")
            ->action('Voir mes documents', $this->portalUrl('/documents'))
            ->line('Merci de le valider dès que possible.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'internal_document.published',
            'document_id' => $this->documentId,
            'title' => $this->title,
            'version' => $this->version,
            'message' => "Nouveau document à valider : « {$this->title} » (v{$this->version}).",
            'url' => '/documents',
        ];
    }
}
