<?php

declare(strict_types=1);

use App\Enums\TicketType;
use App\Filament\Resources\Offers\Pages\CreateOffer;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\EditPurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Subscriptions\Pages\CreateSubscription;
use App\Filament\Resources\Subscriptions\Pages\EditSubscription;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Models\Offer;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C3.3 — Resources catalogue & ventes (Offer, Subscription, Purchase). Rendu testé
 * au niveau composant Livewire (cf. FilamentPanelTest pour l'accès panel/2FA).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

dataset('catalogPages', [
    'catalogue' => [ListOffers::class, CreateOffer::class, EditOffer::class, fn () => Offer::factory()->create()],
    'abonnements' => [ListSubscriptions::class, CreateSubscription::class, EditSubscription::class, fn () => Subscription::factory()->create()],
    'achats' => [ListPurchases::class, CreatePurchase::class, EditPurchase::class, fn () => Purchase::factory()->create()],
]);

it('rend les pages list / create / edit pour un admin', function (string $list, string $create, string $edit, callable $make) {
    Livewire::test($list)->assertOk();
    Livewire::test($create)->assertOk();

    $record = $make();
    Livewire::test($edit, ['record' => $record->getRouteKey()])->assertOk();
})->with('catalogPages');

it('réserve la gestion du catalogue à l\'admin (OfferPolicy)', function () {
    $member = User::factory()->member()->create();
    $offer = Offer::factory()->create();

    $admin = User::factory()->admin()->create();
    expect($admin->can('viewAny', Offer::class))->toBeTrue()
        ->and($admin->can('update', $offer))->toBeTrue()
        ->and($admin->can('delete', $offer))->toBeTrue();

    expect($member->can('viewAny', Offer::class))->toBeFalse()
        ->and($member->can('update', $offer))->toBeFalse();
});

it('refuse à un membre la liste du catalogue', function () {
    actingAs(User::factory()->member()->create());

    Livewire::test(ListOffers::class)->assertForbidden();
});

it('fige le prix à l\'achat et trace l\'admin créateur (Purchase)', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);
    $beneficiary = User::factory()->create();

    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'user_id' => $beneficiary->id,
            'ticket_type' => TicketType::DeskHalfDay->value,
            'quantity' => 5,
            'unit_price_ht' => 12.50,
            'vat_rate' => 20,
            'purchased_at' => now(),
            'label' => 'Pack 5 demi-journées',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $purchase = Purchase::firstOrFail();
    expect($purchase->created_by)->toBe($admin->id)
        ->and((float) $purchase->unit_price_ht)->toBe(12.50)
        ->and($purchase->quantity)->toBe(5);
});
