<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tickets\Pages;

use App\Enums\TicketType;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PurchaseService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Crédit manuel (geste commercial, PRD §4.8.1) : tickets SANS achat,
            // traçant l'admin créditeur et la raison — via PurchaseService.
            Action::make('creditManual')
                ->label('Crédit manuel')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->authorize(fn (): bool => Auth::user()?->can('create', Ticket::class) === true)
                ->modalHeading('Créditer manuellement des tickets')
                ->modalDescription('Personne venue sans réserver, offre cadeau, compensation… Les tickets créés sont immédiatement disponibles, sans achat rattaché.')
                ->schema([
                    Select::make('user_id')
                        ->label('Membre')
                        ->options(fn (): array => User::query()
                            ->orderBy('last_name')
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->fullName()} ({$user->email})"])
                            ->all())
                        ->searchable()
                        ->required(),
                    Select::make('type')
                        ->label('Type de ticket')
                        ->options(TicketType::class)
                        ->required(),
                    TextInput::make('quantity')
                        ->label('Quantité')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(1)
                        ->required(),
                    Textarea::make('reason')
                        ->label('Raison du crédit')
                        ->rows(3)
                        ->maxLength(500)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    // `options(TicketType::class)` fait ré-hydrater l'état en enum.
                    $type = $data['type'] instanceof TicketType ? $data['type'] : TicketType::from((string) $data['type']);

                    $tickets = app(PurchaseService::class)->creditManual(
                        holder: User::query()->findOrFail((int) $data['user_id']),
                        type: $type,
                        quantity: (int) $data['quantity'],
                        reason: (string) $data['reason'],
                        creditedBy: (int) Auth::id(),
                    );

                    Notification::make()
                        ->success()
                        ->title('Tickets crédités')
                        ->body("{$tickets->count()} ticket(s) crédité(s).")
                        ->send();
                }),
        ];
    }
}
