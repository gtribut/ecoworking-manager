<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotifiedAction;
use App\Models\Booking;

/**
 * Réservation créée, modifiée ou annulée **par le back-office** pour le compte
 * d'un membre (PRD §3.8.4, « résa confirmée (création/modification/suppression
 * admin) »). Purement in-app : ce n'est pas un événement critique au sens du
 * PRD (les seuls doublés par email sont facture émise/en retard et document à
 * valider). Le ciblage (acteur ≠ propriétaire) est fait par BookingObserver.
 *
 * Charge utile sans PII : salle, créneau, action — jamais l'identité de
 * l'admin qui a agi (elle est dans l'audit log, pas dans la cloche).
 */
final class BookingChangedNotification extends PortalNotification
{
    protected bool $emailable = false;

    /**
     * Réservation figée À LA CONSTRUCTION plutôt que portée en modèle : la
     * notification part en queue, et sur une SUPPRESSION la ligne n'existe
     * déjà plus quand le worker la traite — `SerializesModels` échouerait à la
     * recharger (ModelNotFoundException, job en échec, membre jamais prévenu).
     *
     * @var array{id: int|null, room: string, slot: string, starts_at: string|null}
     */
    private readonly array $booking;

    public function __construct(Booking $booking, private readonly NotifiedAction $action)
    {
        $this->booking = [
            'id' => $booking->id,
            'room' => $booking->resource?->name ?? 'une salle',
            'slot' => self::slot($booking),
            'starts_at' => $booking->starts_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => "booking.{$this->action->value}",
            'booking_id' => $this->booking['id'],
            'resource_name' => $this->booking['room'],
            'starts_at' => $this->booking['starts_at'],
            'message' => $this->message(),
            'url' => '/bookings',
        ];
    }

    private function message(): string
    {
        $room = $this->booking['room'];
        $slot = $this->booking['slot'];

        return match ($this->action) {
            NotifiedAction::Created => "L'accueil a réservé {$room} pour vous {$slot}.",
            NotifiedAction::Updated => "Votre réservation {$room} a été modifiée par l'accueil : {$slot}.",
            NotifiedAction::Removed => "Votre réservation {$room} {$slot} a été annulée par l'accueil.",
        };
    }

    /**
     * Créneau en toutes lettres. Les dates sont formatées telles que lues :
     * l'heure murale est stable au round-trip (piège fuseau du dépôt), seule
     * une comparaison absolue mentirait — il n'y en a aucune ici.
     */
    private static function slot(Booking $booking): string
    {
        $start = $booking->starts_at;

        if ($start === null) {
            return '';
        }

        $end = $booking->ends_at;

        return 'le '.$start->format('d/m/Y')
            .' de '.$start->format('H\hi')
            .($end === null ? '' : ' à '.$end->format('H\hi'));
    }
}
