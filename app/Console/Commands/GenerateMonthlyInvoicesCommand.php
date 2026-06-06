<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MonthlyBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Génère les brouillons de facturation récurrente du mois (C6.5, PRD §5.1).
 * Idempotent : peut être relancé sans créer de doublon. Lancé par le scheduler
 * le 1er du mois, ou manuellement (`--month=YYYY-MM`).
 */
final class GenerateMonthlyInvoicesCommand extends Command
{
    protected $signature = 'invoices:generate-monthly {--month= : Mois ciblé au format YYYY-MM (défaut : mois courant)}';

    protected $description = 'Génère les brouillons de factures récurrentes du mois (idempotent).';

    public function handle(MonthlyBillingService $billing): int
    {
        $monthOption = $this->option('month');
        $month = $monthOption !== null
            ? CarbonImmutable::createFromFormat('Y-m', $monthOption)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $created = $billing->generateMonth($month);

        $this->info(sprintf(
            '%d brouillon(s) de facture généré(s) pour %s.',
            $created->count(),
            $month->format('Y-m'),
        ));

        return self::SUCCESS;
    }
}
