<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Passe en `overdue` les factures émises échues et non soldées (C6.6).
 * Lancé quotidiennement par le scheduler. Les avoirs et brouillons sont exclus.
 */
final class UpdateOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:update-overdue';

    protected $description = 'Marque comme « en retard » les factures émises échues et non soldées.';

    public function handle(): int
    {
        $count = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::PartiallyPaid->value])
            ->whereNotNull('number')
            ->where('is_credit_note', false)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', Carbon::today()->toDateString())
            ->whereColumn('amount_paid', '<', 'total_ttc')
            ->update(['status' => InvoiceStatus::Overdue->value]);

        $this->info("{$count} facture(s) passée(s) en retard.");

        return self::SUCCESS;
    }
}
