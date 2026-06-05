<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\DeskOccupations\Pages\CreateDeskOccupation;
use App\Filament\Resources\DeskOccupations\Pages\EditDeskOccupation;
use App\Filament\Resources\DeskOccupations\Pages\ListDeskOccupations;
use App\Filament\Resources\Resources\Pages\CreateResource;
use App\Filament\Resources\Resources\Pages\EditResource;
use App\Filament\Resources\Resources\Pages\ListResources;
use App\Models\Booking;
use App\Models\DeskOccupation;
use App\Models\Resource;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C3.4 — Resources espaces & réservations (Resource, Booking, DeskOccupation).
 * Rendu testé au niveau composant Livewire (le 2FA obligatoire casse l'accès HTTP).
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

dataset('spacesPages', [
    'espaces' => [ListResources::class, CreateResource::class, EditResource::class, fn () => Resource::factory()->create()],
    'réservations' => [ListBookings::class, CreateBooking::class, EditBooking::class, fn () => Booking::factory()->create()],
    'occupations' => [ListDeskOccupations::class, CreateDeskOccupation::class, EditDeskOccupation::class, fn () => DeskOccupation::factory()->create()],
]);

it('rend les pages list / create / edit pour un admin', function (string $list, string $create, string $edit, callable $make) {
    Livewire::test($list)->assertOk();
    Livewire::test($create)->assertOk();

    $record = $make();
    Livewire::test($edit, ['record' => $record->getRouteKey()])->assertOk();
})->with('spacesPages');

it('réserve la gestion des espaces à l\'admin (ResourcePolicy)', function () {
    $member = User::factory()->member()->create();
    $resource = Resource::factory()->create();

    expect($member->can('viewAny', Resource::class))->toBeFalse()
        ->and($member->can('update', $resource))->toBeFalse();
});

it('refuse à un membre la liste des espaces', function () {
    actingAs(User::factory()->member()->create());

    Livewire::test(ListResources::class)->assertForbidden();
});

it('trace l\'admin créateur sur une réservation back-office', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);
    $room = Resource::factory()->meetingRoom()->create();
    $member = User::factory()->member()->create();

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'resource_id' => $room->id,
            'user_id' => $member->id,
            'status' => BookingStatus::Confirmed->value,
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(10, 0),
            'is_internal' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Booking::firstOrFail()->created_by)->toBe($admin->id);
});
