<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Annulation = BookingService::cancel (statut + horodatage +
            // RESTITUTION du ticket) — jamais un simple changement de statut.
            Action::make('cancel')
                ->label('Annuler la réservation')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Confirmed)
                ->schema([
                    Textarea::make('reason')
                        ->label('Motif de l\'annulation')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalDescription('La réservation passera en « annulée » et le ticket éventuel sera restitué.')
                ->action(function (Booking $record, array $data): void {
                    app(BookingService::class)->cancel($record, $data['reason'] ?? null);
                    $this->refreshFormData(['status', 'cancelled_at', 'cancel_reason']);
                })
                ->successNotificationTitle('Réservation annulée'),
            DeleteAction::make(),
        ];
    }

    /**
     * Passe par BookingService::update (verrou anti-chevauchement + backstop
     * GiST) : un UPDATE direct renverrait une QueryException brute (500).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            /** @var Booking $record */
            return app(BookingService::class)->update($record, $data);
        } catch (BookingConflictException $e) {
            throw ValidationException::withMessages(['data.starts_at' => $e->getMessage()]);
        }
    }
}
