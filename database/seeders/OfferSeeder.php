<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Enums\OfferType;
use App\Enums\SubscriberKind;
use App\Enums\TicketType;
use App\Models\Offer;
use Illuminate\Database\Seeder;

/**
 * Catalogue MVP — 8 SKU (PRD §4.5.1 / §6.3). Tous montants HT, TVA FR 20 %.
 * Les packs sont des SKU distincts (CLAUDE.md §3.6). Idempotent (updateOrCreate
 * sur `code`) : rejouable sans doublon, et réaligne les prix au catalogue courant.
 */
class OfferSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->offers() as $offer) {
            Offer::updateOrCreate(['code' => $offer['code']], $offer);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function offers(): array
    {
        return [
            [
                'code' => 'resident_desk',
                'name' => 'Bureau résident (temps plein)',
                'description' => 'Abonnement mensuel pour un bureau résident attitré.',
                'type' => OfferType::Subscription->value,
                'subscriber_kind' => SubscriberKind::Member->value,
                'billing_period' => BillingPeriod::Monthly->value,
                'unit_price_ht' => 328.50,
                'vat_rate' => 20.00,
                'quantity_per_purchase' => 1,
                'requires_active_resident' => false,
                'is_active' => true,
                'is_public' => true,
                'display_order' => 1,
            ],
            [
                'code' => 'additional_person',
                'name' => 'Personne supplémentaire',
                'description' => 'Abonnement mensuel pour une personne additionnelle (≥1 bureau résident actif requis).',
                'type' => OfferType::Subscription->value,
                'subscriber_kind' => SubscriberKind::Member->value,
                'billing_period' => BillingPeriod::Monthly->value,
                'unit_price_ht' => 59.00,
                'vat_rate' => 20.00,
                'quantity_per_purchase' => 1,
                'requires_active_resident' => true,
                'is_active' => true,
                'is_public' => true,
                'display_order' => 2,
            ],
            [
                'code' => 'domiciliation',
                'name' => 'Domiciliation juridique',
                'description' => 'Domiciliation du siège social. Abonnement d\'entité, 1 par entité (≥1 résident actif requis).',
                'type' => OfferType::Subscription->value,
                'subscriber_kind' => SubscriberKind::Entity->value,
                'billing_period' => BillingPeriod::Monthly->value,
                'unit_price_ht' => 35.00,
                'vat_rate' => 20.00,
                'quantity_per_purchase' => 1,
                'requires_active_resident' => true,
                'is_active' => true,
                'is_public' => true,
                'display_order' => 3,
            ],
            [
                'code' => 'desk_half_day',
                'name' => 'Ticket bureau ½ journée',
                'description' => 'Un ticket bureau libre pour une demi-journée (matin ou après-midi).',
                'type' => OfferType::OneShot->value,
                'subscriber_kind' => SubscriberKind::Member->value,
                'billing_period' => BillingPeriod::OneTime->value,
                'unit_price_ht' => 17.50,
                'vat_rate' => 20.00,
                'quantity_per_purchase' => 1,
                'ticket_type' => TicketType::DeskHalfDay->value,
                'is_active' => true,
                'is_public' => true,
                'display_order' => 4,
            ],
            [
                'code' => 'desk_half_day_pack_2',
                'name' => 'Pack 2 tickets bureau ½ journée',
                'description' => 'Pack de 2 tickets bureau ½ journée (= 1 journée, −10 %).',
                'type' => OfferType::Pack->value,
                'subscriber_kind' => SubscriberKind::Member->value,
                'billing_period' => BillingPeriod::OneTime->value,
                'unit_price_ht' => 31.50,
                'vat_rate' => 20.00,
                'quantity_per_purchase' => 2,
                'ticket_type' => TicketType::DeskHalfDay->value,
                'is_active' => true,
                'is_public' => true,
                'display_order' => 5,
            ],
            [
                'code' => 'desk_half_day_pack_10',
                'name' => 'Pack 10 tickets bureau ½ journée',
                'description' => 'Pack de 10 tickets bureau ½ journée (−20 %).',
                'type' => OfferType::Pack->value,
                'subscriber_kind' => SubscriberKind::Member->value,
                'billing_period' => BillingPeriod::OneTime->value,
                'unit_price_ht' => 140.00,
                'vat_rate' => 20.00,
                'quantity_per_purchase' => 10,
                'ticket_type' => TicketType::DeskHalfDay->value,
                'is_active' => true,
                'is_public' => true,
                'display_order' => 6,
            ],
            [
                'code' => 'meeting_room_half_day',
                'name' => 'Ticket salle réunion ½ journée',
                'description' => 'Un ticket salle de réunion pour une demi-journée (matin ou après-midi).',
                'type' => OfferType::OneShot->value,
                'subscriber_kind' => SubscriberKind::Member->value,
                'billing_period' => BillingPeriod::OneTime->value,
                'unit_price_ht' => 71.00,
                'vat_rate' => 20.00,
                'quantity_per_purchase' => 1,
                'ticket_type' => TicketType::MeetingRoomHalfDay->value,
                'is_active' => true,
                'is_public' => true,
                'display_order' => 7,
            ],
            [
                'code' => 'meeting_room_half_day_pack_10',
                'name' => 'Pack 10 tickets salle réunion ½ journée',
                'description' => 'Pack de 10 tickets salle de réunion ½ journée (−20 %).',
                'type' => OfferType::Pack->value,
                'subscriber_kind' => SubscriberKind::Member->value,
                'billing_period' => BillingPeriod::OneTime->value,
                'unit_price_ht' => 568.00,
                'vat_rate' => 20.00,
                'quantity_per_purchase' => 10,
                'ticket_type' => TicketType::MeetingRoomHalfDay->value,
                'is_active' => true,
                'is_public' => true,
                'display_order' => 8,
            ],
        ];
    }
}
