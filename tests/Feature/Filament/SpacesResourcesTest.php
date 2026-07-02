<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
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
use App\Models\Ticket;
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
            // Pas de champ statut à la création : BookingService pose `confirmed`.
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(10, 0),
            'is_internal' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = Booking::firstOrFail();
    expect($booking->created_by)->toBe($admin->id)
        ->and($booking->status)->toBe(BookingStatus::Confirmed);
});

it('refuse un chevauchement à la création back-office (erreur de formulaire, pas de 500)', function () {
    actingAs(User::factory()->admin()->create());
    $room = Resource::factory()->meetingRoom()->create();
    $member = User::factory()->member()->create();
    Booking::factory()->create([
        'resource_id' => $room->id, 'user_id' => $member->id,
        'status' => BookingStatus::Confirmed->value,
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'resource_id' => $room->id,
            'user_id' => $member->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
            'is_internal' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['starts_at']);

    expect(Booking::query()->count())->toBe(1);
});

it('refuse un chevauchement à la modification back-office', function () {
    actingAs(User::factory()->admin()->create());
    $room = Resource::factory()->meetingRoom()->create();
    $member = User::factory()->member()->create();
    Booking::factory()->create([
        'resource_id' => $room->id, 'user_id' => $member->id,
        'status' => BookingStatus::Confirmed->value,
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ]);
    $booking = Booking::factory()->create([
        'resource_id' => $room->id, 'user_id' => $member->id,
        'status' => BookingStatus::Confirmed->value,
        'starts_at' => now()->addDay()->setTime(14, 0),
        'ends_at' => now()->addDay()->setTime(15, 0),
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->fillForm([
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ])
        ->call('save')
        ->assertHasFormErrors(['starts_at']);

    expect($booking->refresh()->starts_at->format('H'))->toBe('14');
});

it('annule une résa via l\'action dédiée et restitue le ticket (back-office)', function () {
    actingAs(User::factory()->admin()->create());
    $ticket = Ticket::factory()->create([
        'type' => TicketType::MeetingRoomHalfDay->value,
        'status' => TicketStatus::Used->value,
    ]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed->value,
        'ticket_id' => $ticket->id,
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Erreur de saisie']);

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancel_reason)->toBe('Erreur de saisie')
        ->and($booking->cancelled_at)->not->toBeNull()
        ->and($ticket->refresh()->status)->toBe(TicketStatus::Available);
});

it('restitue le ticket consommé à la suppression physique d\'une résa', function () {
    $ticket = Ticket::factory()->create([
        'type' => TicketType::MeetingRoomHalfDay->value,
        'status' => TicketStatus::Used->value,
    ]);
    $booking = Booking::factory()->create(['ticket_id' => $ticket->id]);

    $booking->delete();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Available);
});
