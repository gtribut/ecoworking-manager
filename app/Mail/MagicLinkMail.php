<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Lien de connexion par email (magic link, C12.8a — PRD §3.2).
 *
 * Porte l'URL signée complète (jeton inclus) : ne jamais logger cette URL.
 * Envoyée en queue — l'envoi ne bloque pas la requête HTTP.
 */
final class MagicLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $url,
        public readonly int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre lien de connexion — Portail Ecoworking',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.magic-link');
    }
}
