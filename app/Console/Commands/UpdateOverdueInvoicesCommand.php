<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

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
        // On récupère les factures concernées AVANT la bascule pour pouvoir
        // notifier leurs destinataires (C8) : le passage `overdue` est unique
        // (le statut ne revient pas à `sent`), donc une seule notif par facture.
        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::PartiallyPaid->value])
            ->whereNotNull('number')
            ->where('is_credit_note', false)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', Carbon::today()->toDateString())
            ->whereColumn('amount_paid', '<', 'total_ttc')
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->update(['status' => InvoiceStatus::Overdue->value]);
            Notification::send($invoice->recipients(), new InvoiceOverdueNotification($invoice));
        }

        $this->info("{$invoices->count()} facture(s) passée(s) en retard.");

        return self::SUCCESS;
    }
}
