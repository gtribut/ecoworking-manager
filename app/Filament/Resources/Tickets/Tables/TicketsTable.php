<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tickets\Tables;

use App\Enums\Period;
use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Exceptions\DomainActionException;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DeskAvailabilityService;
use App\Services\ManualTicketConsumptionService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'user', 'purchase', 'creditedBy', 'booking.resource', 'deskOccupation.desk',
            ]))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('user.last_name')
                    ->label('Membre')
                    ->formatStateUsing(fn ($record): string => $record->user?->fullName() ?? '—')
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->weight('medium'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('purchase.label')
                    ->label('Achat parent')
                    ->description(fn ($record): ?string => $record->purchase !== null ? "Achat #{$record->purchase->id}" : null)
                    ->placeholder('Crédit manuel')
                    ->toggleable(),
                TextColumn::make('consumed_at')
                    ->label('Utilisé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('usage')
                    ->label('Ressource utilisée')
                    ->getStateUsing(fn ($record): ?string => $record->booking?->resource?->name
                        ?? $record->deskOccupation?->desk?->name)
                    ->placeholder('—'),
                TextColumn::make('creditedBy.last_name')
                    ->label('Crédité par')
                    ->formatStateUsing(fn ($record): string => $record->creditedBy?->fullName() ?? '—')
                    ->description(fn ($record): ?string => $record->credit_reason)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Membre')
                    ->relationship('user', 'email')
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->fullName()} ({$record->email})")
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')
                    ->label('Type de ticket')
                    ->options(TicketType::class),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(TicketStatus::class),
            ])
            ->recordActions([
                self::consumeAction(),
            ])
            ->defaultSort('id', 'desc');
    }

    /**
     * Consommation manuelle (PRD §4.8.1) : l'admin décompte un ticket à la
     * volée pour un external présent sur place. Toute la logique métier vit
     * dans ManualTicketConsumptionService — ici uniquement le formulaire.
     */
    private static function consumeAction(): Action
    {
        return Action::make('consume')
            ->label('Consommer')
            ->icon(Heroicon::OutlinedBolt)
            ->color('warning')
            ->visible(fn (Ticket $record): bool => $record->status === TicketStatus::Available
                && Auth::user()?->can('update', $record) === true)
            ->modalHeading('Consommer manuellement ce ticket')
            ->modalDescription(fn (Ticket $record): string => sprintf(
                'Ticket « %s » de %s : le créneau choisi créera l\'occupation ou la réservation correspondante.',
                $record->type?->getLabel() ?? '—',
                $record->user?->fullName() ?? '—',
            ))
            ->schema([
                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->live(),
                Select::make('period')
                    ->label('Créneau')
                    ->options([
                        Period::Morning->value => Period::Morning->getLabel(),
                        Period::Afternoon->value => Period::Afternoon->getLabel(),
                    ])
                    ->required()
                    ->live(),
                Select::make('resource_id')
                    ->label(fn (Ticket $record): string => $record->type === TicketType::MeetingRoomHalfDay ? 'Salle' : 'Bureau')
                    ->options(fn (Ticket $record, Get $get): array => self::consumableResourceOptions($record, $get))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Ticket $record, array $data, Action $action): void {
                try {
                    app(ManualTicketConsumptionService::class)->consume(
                        $record,
                        Resource::query()->findOrFail((int) $data['resource_id']),
                        CarbonImmutable::parse((string) $data['date']),
                        Period::from((string) $data['period']),
                        (int) Auth::id(),
                    );
                } catch (DomainActionException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Consommation impossible')
                        ->body($e->getMessage())
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title('Ticket consommé')
                    ->send();
            });
    }

    /**
     * Options de la cible selon le type de ticket. Simple aide à la saisie
     * (bureaux nomades libres si date + créneau saisis) — la garde métier
     * reste dans ManualTicketConsumptionService.
     *
     * @return array<int, string>
     */
    private static function consumableResourceOptions(Ticket $record, Get $get): array
    {
        if ($record->type === TicketType::MeetingRoomHalfDay) {
            return Resource::query()
                ->where('type', ResourceType::MeetingRoom->value)
                ->where('is_active', true)
                ->where('is_out_of_service', false)
                ->orderBy('display_order')
                ->pluck('name', 'id')
                ->all();
        }

        $date = $get('date');
        $period = $get('period');

        if (filled($date) && filled($period) && $period !== Period::FullDay->value) {
            return app(DeskAvailabilityService::class)
                ->availableDesks(CarbonImmutable::parse((string) $date), Period::from((string) $period))
                ->pluck('name', 'id')
                ->all();
        }

        return Resource::query()
            ->where('type', ResourceType::Desk->value)
            ->where('assignment', ResourceAssignment::Unassigned->value)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->pluck('name', 'id')
            ->all();
    }
}
