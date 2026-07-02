<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoicePaymentService;

/**
 * Maintient `invoices.amount_paid` et le statut de paiement synchronisés avec
 * les paiements (C6.6). Couvre création, modification (montant), suppression
 * soft et restauration.
 */
final class PaymentObserver
{
    public function __construct(private readonly InvoicePaymentService $payments) {}

    public function created(Payment $payment): void
    {
        $this->sync($payment);
    }

    public function updated(Payment $payment): void
    {
        // Paiement réaffecté à une autre facture : l'ancienne doit être
        // recalculée elle aussi, sinon elle reste `paid` à tort pour toujours.
        if ($payment->wasChanged('invoice_id')) {
            $previous = Invoice::query()->find($payment->getOriginal('invoice_id'));
            if ($previous !== null) {
                $this->payments->recalculate($previous);
            }
        }

        $this->sync($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->sync($payment);
    }

    public function restored(Payment $payment): void
    {
        $this->sync($payment);
    }

    private function sync(Payment $payment): void
    {
        // Lecture par FK et non via la relation : après une réaffectation,
        // `$payment->invoice` peut encore pointer (cache) l'ancienne facture.
        $invoice = Invoice::query()->find($payment->invoice_id);

        if ($invoice !== null) {
            $this->payments->recalculate($invoice);
        }
    }
}
