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
 * Email d'accueil envoyé à la création d'un compte par l'admin (PRD §3.2).
 *
 * Ne contient JAMAIS de mot de passe : uniquement un lien de définition
 * initiale, porteur d'un jeton du broker `welcome` (3 jours). Comme le magic
 * link, cette URL ne doit jamais être journalisée. Envoyée en queue.
 */
final class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $firstName,
        public readonly string $url,
        public readonly int $ttlDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenue chez Ecoworking — activez votre compte',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.welcome');
    }
}
