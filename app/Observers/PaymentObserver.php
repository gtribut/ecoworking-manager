<?php

declare(strict_types=1);

namespace App\Observers;

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
        $invoice = $payment->invoice;

        if ($invoice !== null) {
            $this->payments->recalculate($invoice);
        }
    }
}
