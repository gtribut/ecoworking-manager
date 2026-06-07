<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Facture passée en retard (PRD §3.8.4) — événement critique doublé par email.
 * Émise par le passage `overdue` (commande quotidienne C6.6), une seule fois
 * par facture (le statut ne revient pas à `sent`).
 */
final class InvoiceOverdueNotification extends PortalNotification
{
    public function __construct(private readonly Invoice $invoice) {}

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->invoice->number ?? '';

        return (new MailMessage)
            ->subject("Facture {$number} en retard de paiement")
            ->greeting('Bonjour,')
            ->line("La facture {$number} ({$this->amount()} € TTC), échue le "
                .($this->invoice->due_at?->format('d/m/Y') ?? '—').", n'a pas encore été réglée.")
            ->action('Régulariser', $this->portalUrl('/factures'))
            ->line('Si le règlement a déjà été effectué, merci d\'ignorer ce message.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'invoice.overdue',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->number,
            'amount_ttc' => $this->amount(),
            'message' => "Facture {$this->invoice->number} en retard de paiement.",
            'url' => '/factures',
        ];
    }

    private function amount(): string
    {
        return number_format((float) $this->invoice->total_ttc, 2, ',', ' ');
    }
}
