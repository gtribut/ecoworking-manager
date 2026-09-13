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
 * Aucune PII dans la charge utile : seuls le titre et la version du document.
 */
final class InternalDocumentPublishedNotification extends PortalNotification
{
    public function __construct(private readonly InternalDocument $document) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nouveau document à valider : {$this->document->title}")
            ->greeting('Bonjour,')
            ->line("Le document « {$this->document->title} » (version {$this->document->version}) est à lire et à valider dans votre espace membre.")
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
            'document_id' => $this->document->id,
            'title' => $this->document->title,
            'version' => $this->document->version,
            'message' => "Nouveau document à valider : « {$this->document->title} » (v{$this->document->version}).",
            'url' => '/documents',
        ];
    }
}
