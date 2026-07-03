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

it('seede l\'inventaire : 49 bureaux (29 + 20) + 3 salles réunion + 1 salle event', function () {
    $this->seed(ResourceSeeder::class);

    expect(Resource::where('type', 'desk')->count())->toBe(49)
        // Décision Guillaume 2026-07-03 : étage 1 = 29 bureaux, étage 2 = 20.
        ->and(Resource::where('type', 'desk')->where('floor', 1)->count())->toBe(29)
        ->and(Resource::where('type', 'desk')->where('floor', 2)->count())->toBe(20)
        // Correspondance plan ↔ DB : ids identiques à ceux du SVG (non paddés).
        ->and(Resource::where('svg_desk_id', 'desk-1')->value('floor'))->toBe(1)
        ->and(Resource::where('svg_desk_id', 'desk-29')->value('floor'))->toBe(1)
        ->and(Resource::where('svg_desk_id', 'desk-30')->value('floor'))->toBe(2)
        ->and(Resource::where('svg_desk_id', 'desk-49')->value('floor'))->toBe(2)
        ->and(Resource::where('type', 'meeting_room')->count())->toBe(3)
        ->and(Resource::where('type', 'event_room')->count())->toBe(1)
        ->and(Resource::where('type', 'event_room')->value('requires_admin'))->toBeTrue()
        ->and(Resource::where('type', 'meeting_room')->value('external_half_day_price_ht'))->toBe('71.00');
});

it('migre les svg_desk_id historiques zéro-paddés sans créer de doublon', function () {
    Resource::factory()->create([
        'type' => 'desk',
        'svg_desk_id' => 'desk-03',
        'name' => 'Bureau 3',
    ]);

    $this->seed(ResourceSeeder::class);

    expect(Resource::where('type', 'desk')->count())->toBe(49)
        ->and(Resource::where('svg_desk_id', 'desk-03')->exists())->toBeFalse()
        ->and(Resource::where('svg_desk_id', 'desk-3')->count())->toBe(1);
});

it('est idempotent : rejouable sans doublon', function () {
    $this->seed(OfferSeeder::class);
    $this->seed(OfferSeeder::class);
    $this->seed(ResourceSeeder::class);
    $this->seed(ResourceSeeder::class);

    expect(Offer::count())->toBe(8)
        ->and(Resource::count())->toBe(53);
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
