<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Statut d'une facture (data_model §3, `invoices.status`). */
enum InvoiceStatus: string implements HasColor, HasLabel
{
    use HasValues;

    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Émise',
            self::Paid => 'Payée',
            self::PartiallyPaid => 'Partiellement payée',
            self::Overdue => 'En retard',
            self::Cancelled => 'Annulée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Paid => 'success',
            self::PartiallyPaid => 'warning',
            self::Overdue => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
