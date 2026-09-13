<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Facture émise (PRD §3.8.4) — événement critique doublé par email. Adressée
 * aux contacts facturation du périmètre de la facture (cf. Invoice::recipients).
 * Le montant et le numéro sont figés à l'émission ; on les lit tels quels.
 */
final class InvoiceIssuedNotification extends PortalNotification
{
    public function __construct(private readonly Invoice $invoice) {}

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->invoice->number ?? '';

        return (new MailMessage)
            ->subject("Nouvelle facture {$number}")
            ->greeting('Bonjour,')
            ->line("La facture {$number} d'un montant de {$this->amount()} € TTC vient d'être émise.")
            ->line('Échéance de paiement : '.($this->invoice->due_at?->format('d/m/Y') ?? 'à réception').'.')
            ->action('Voir mes factures', $this->portalUrl('/invoices'))
            ->line('Merci de votre confiance.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'invoice.issued',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->number,
            'amount_ttc' => $this->amount(),
            'message' => "Nouvelle facture {$this->invoice->number} ({$this->amount()} € TTC).",
            // Route réelle du portail (App.tsx) : /invoices, pas /factures (lot G).
            'url' => '/invoices',
        ];
    }

    private function amount(): string
    {
        return number_format((float) $this->invoice->total_ttc, 2, ',', ' ');
    }
}
