<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Offer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Création d'achats (tickets/packs) et génération des tickets associés (C7.5).
 * Le prix est SNAPSHOTÉ à l'achat (contrairement aux abonnements). L'achat est
 * crédité par l'admin en MVP. data_model §4.2.
 */
final class PurchaseService
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * Crée un achat depuis une offre catalogue et génère les N tickets
     * (N = `quantity_per_purchase`) au statut `available`. Prix figé.
     *
     * @param  Model  $billable  entité facturée (User ou Company)
     */
    public function createFromOffer(Offer $offer, User $holder, Model $billable, int $createdBy): array
    {
        return $this->db->transaction(function () use ($offer, $holder, $billable, $createdBy): array {
            $quantity = max(1, (int) $offer->quantity_per_purchase);

            $purchase = $offer->purchases()->create([
                'user_id' => $holder->id,
                'billable_type' => $billable->getMorphClass(),
                'billable_id' => $billable->getKey(),
                'ticket_type' => $offer->ticket_type?->value,
                'quantity' => $quantity,
                'unit_price_ht' => $offer->unit_price_ht,
                'vat_rate' => $offer->vat_rate,
                'label' => $offer->name,
                'purchased_at' => Carbon::now(),
                'created_by' => $createdBy,
            ]);

            $tickets = $this->generateTickets(
                holder: $holder,
                type: $offer->ticket_type,
                quantity: $quantity,
                purchaseId: $purchase->id,
            );

            return ['purchase' => $purchase, 'tickets' => $tickets];
        });
    }

    /**
     * Crédit manuel (geste commercial) : tickets sans achat, traçant l'admin
     * créditeur et la raison.
     *
     * @return Collection<int, Ticket>
     */
    public function creditManual(User $holder, TicketType $type, int $quantity, string $reason, int $creditedBy): Collection
    {
        return $this->db->transaction(fn (): Collection => $this->generateTickets(
            holder: $holder,
            type: $type,
            quantity: max(1, $quantity),
            creditedBy: $creditedBy,
            creditReason: $reason,
        ));
    }

    /**
     * @return Collection<int, Ticket>
     */
    private function generateTickets(
        User $holder,
        ?TicketType $type,
        int $quantity,
        ?int $purchaseId = null,
        ?int $creditedBy = null,
        ?string $creditReason = null,
    ): Collection {
        $tickets = new Collection;

        for ($i = 0; $i < $quantity; $i++) {
            $tickets->push(Ticket::create([
                'purchase_id' => $purchaseId,
                'user_id' => $holder->id,
                'type' => $type?->value,
                'status' => TicketStatus::Available->value,
                'credited_by' => $creditedBy,
                'credit_reason' => $creditReason,
            ]));
        }

        return $tickets;
    }
}
