<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tickets;

use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Tables\TicketsTable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Tickets nomades côté admin (PRD §4.8.1, C12.2). LECTURE SEULE sur le cycle
 * de vie : le statut vit exclusivement via les services (consommation à la
 * réservation, restitution à l'annulation). Pas de create/edit/delete —
 * seules les actions métier « Crédit manuel » (PurchaseService::creditManual)
 * et « Consommation manuelle » (ManualTicketConsumptionService) existent.
 */
class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue & ventes';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'ticket';

    protected static ?string $pluralModelLabel = 'tickets';

    protected static ?string $navigationLabel = 'Tickets';

    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
    }

    /** Jamais de création à la main : crédit via facture ou action dédiée. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
        ];
    }
}
