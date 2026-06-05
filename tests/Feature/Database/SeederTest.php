<?php

declare(strict_types=1);

use App\Models\Offer;
use App\Models\Resource;
use Database\Seeders\OfferSeeder;
use Database\Seeders\ResourceSeeder;

it('seede le catalogue MVP (8 SKU) avec les prix figés du PRD', function () {
    $this->seed(OfferSeeder::class);

    expect(Offer::count())->toBe(8)
        ->and(Offer::where('code', 'resident_desk')->value('unit_price_ht'))->toBe('328.50')
        ->and(Offer::where('code', 'domiciliation')->value('unit_price_ht'))->toBe('35.00')
        ->and(Offer::where('code', 'desk_half_day_pack_10')->value('quantity_per_purchase'))->toBe(10)
        ->and(Offer::where('code', 'meeting_room_half_day_pack_10')->value('unit_price_ht'))->toBe('568.00');
});

it('seede l\'inventaire : 48 bureaux + 3 salles réunion + 1 salle event', function () {
    $this->seed(ResourceSeeder::class);

    expect(Resource::where('type', 'desk')->count())->toBe(48)
        ->and(Resource::where('type', 'meeting_room')->count())->toBe(3)
        ->and(Resource::where('type', 'event_room')->count())->toBe(1)
        ->and(Resource::where('type', 'event_room')->value('requires_admin'))->toBeTrue()
        ->and(Resource::where('type', 'meeting_room')->value('external_half_day_price_ht'))->toBe('71.00');
});

it('est idempotent : rejouable sans doublon', function () {
    $this->seed(OfferSeeder::class);
    $this->seed(OfferSeeder::class);
    $this->seed(ResourceSeeder::class);
    $this->seed(ResourceSeeder::class);

    expect(Offer::count())->toBe(8)
        ->and(Resource::count())->toBe(52);
});
