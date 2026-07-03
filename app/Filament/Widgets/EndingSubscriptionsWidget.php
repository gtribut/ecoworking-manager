<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AdminDashboardService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Bloc d'alerte « Abonnements qui se terminent dans les 30 prochains jours »
 * (C12.6, PRD §4.1.2). Widget MINCE : requête dans AdminDashboardService.
 */
class EndingSubscriptionsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = ['lg' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Abonnements se terminant sous 30 jours')
            ->query(fn () => app(AdminDashboardService::class)->endingSubscriptionsQuery())
            ->paginated(false)
            ->emptyStateHeading('Aucune fin d\'abonnement proche')
            ->columns([
                TextColumn::make('offer.name')
                    ->label('Offre'),
                TextColumn::make('subscriber')
                    ->label('Souscripteur')
                    ->getStateUsing(fn (Subscription $record): string => match (true) {
                        $record->subscriber instanceof User => $record->subscriber->fullName(),
                        $record->subscriber instanceof Company => $record->subscriber->name,
                        default => '—',
                    }),
                TextColumn::make('ends_at')
                    ->label('Fin le')
                    ->date('d/m/Y'),
            ]);
    }
}
