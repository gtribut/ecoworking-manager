<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Statut d'une facture (data_model §3, `invoices.status`). */
enum InvoiceStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
}
