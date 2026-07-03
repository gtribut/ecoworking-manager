<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AdminDashboardService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Bloc d'alerte « Factures en retard (top 5) » (C12.6, PRD §4.1.2).
 * Widget MINCE : le prédicat « en retard » vit dans AdminDashboardService.
 */
class OverdueInvoicesWidget extends TableWidget
{
    protected static ?int $sort = 2;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = ['lg' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Factures en retard (top 5)')
            ->query(fn () => app(AdminDashboardService::class)->overdueInvoicesQuery()->with('billable')->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Aucune facture en retard')
            ->columns([
                TextColumn::make('number')
                    ->label('Numéro')
                    ->weight('medium'),
                TextColumn::make('billable')
                    ->label('Facturé à')
                    ->getStateUsing(fn (Invoice $record): string => match (true) {
                        $record->billable instanceof User => $record->billable->fullName(),
                        $record->billable instanceof Company => $record->billable->name,
                        default => '—',
                    }),
                TextColumn::make('due_at')
                    ->label('Échéance')
                    ->date('d/m/Y'),
                TextColumn::make('total_ttc')
                    ->label('Total TTC')
                    ->money('EUR'),
                TextColumn::make('amount_paid')
                    ->label('Payé')
                    ->money('EUR'),
            ]);
    }
}
