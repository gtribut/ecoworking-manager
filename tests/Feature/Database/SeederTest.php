<?php

declare(strict_types=1);

use App\Models\Offer;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OfferSeeder;
use Database\Seeders\ResourceSeeder;
use Illuminate\Support\Facades\Hash;

it('seede le catalogue MVP (8 SKU) avec les prix figés du PRD', function () {
    $this->seed(OfferSeeder::class);

    expect(Offer::count())->toBe(8)
        ->and(Offer::where('code', 'resident_desk')->value('unit_price_ht'))->toBe('328.50')
        ->and(Offer::where('code', 'domiciliation')->value('unit_price_ht'))->toBe('35.00')
        ->and(Offer::where('code', 'desk_half_day_pack_10')->value('quantity_per_purchase'))->toBe(10)
        ->and(Offer::where('code', 'meeting_room_half_day_pack_10')->value('unit_price_ht'))->toBe('568.00')
        // data_model §4.4 : abonnements en monthly, tickets/packs en one_time.
        ->and(Offer::where('billing_period', 'monthly')->count())->toBe(3)
        ->and(Offer::where('billing_period', 'one_time')->count())->toBe(5);
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

it('rend le DatabaseSeeder rejouable : un seul compte admin (review 06 M6)', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class); // 2e run : pas de violation d'unique

    $admins = User::where('email', 'admin@ecoworking.fr')->get();
    expect($admins)->toHaveCount(1)
        ->and($admins->first()->hasRole('admin'))->toBeTrue()
        // Jamais le mot de passe par défaut des factories sur une adresse réelle.
        ->and(Hash::check('password', $admins->first()->password))->toBeFalse();
});
