<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Company;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;

/**
 * PRD §3.5.2 / §3.5.4 — calendrier des salles : toutes les salles affichées
 * simultanément (3 salles de réunion + salle événementielle en lecture seule),
 * occupants visibles (Q4 : transparence par défaut), sans PII au-delà du
 * prénom + nom + entité + libellé.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class); // câble rôles → permissions
});

function calendarDay(): CarbonImmutable
{
    return CarbonImmutable::today()->addDays(3);
}

// --- Catalogue des salles -------------------------------------------------

it('expose la salle événementielle en lecture seule dans le catalogue', function () {
    $meeting = Resource::factory()->meetingRoom()->create(['display_order' => 1]);
    $event = Resource::factory()->eventRoom()->create(['display_order' => 2]);

    $rooms = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/rooms')->assertOk()->json('data');

    expect(collect($rooms)->pluck('id')->all())->toBe([$meeting->id, $event->id])
        ->and(collect($rooms)->firstWhere('id', $meeting->id)['is_bookable'])->toBeTrue()
        ->and(collect($rooms)->firstWhere('id', $event->id)['is_bookable'])->toBeFalse();
});

it('exclut du catalogue les salles inactives ou hors service', function () {
    $active = Resource::factory()->meetingRoom()->create();
    Resource::factory()->meetingRoom()->create(['is_active' => false]);
    Resource::factory()->meetingRoom()->outOfService()->create();

    $rooms = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/rooms')->assertOk()->json('data');

    expect(collect($rooms)->pluck('id')->all())->toBe([$active->id]);
});

// --- Disponibilité multi-salles -------------------------------------------

it('renvoie les créneaux occupés de toutes les salles sur une plage', function () {
    $viewer = User::factory()->resident()->create();
    $roomA = Resource::factory()->meetingRoom()->create();
    $roomB = Resource::factory()->meetingRoom()->create();
    $event = Resource::factory()->eventRoom()->create();
    $day = calendarDay();

    $mine = Booking::factory()->create([
        'user_id' => $viewer->id, 'resource_id' => $roomA->id, 'title' => 'Point équipe',
        'starts_at' => $day->setTime(10, 0), 'ends_at' => $day->setTime(11, 0),
    ]);
    Booking::factory()->create([
        'resource_id' => $roomB->id,
        'starts_at' => $day->setTime(14, 0), 'ends_at' => $day->setTime(15, 0),
    ]);
    Booking::factory()->create([
        'resource_id' => $event->id,
        'starts_at' => $day->setTime(9, 0), 'ends_at' => $day->setTime(18, 0),
    ]);
    // Hors plage et annulée : jamais renvoyées.
    Booking::factory()->create([
        'resource_id' => $roomA->id,
        'starts_at' => $day->addDays(20)->setTime(10, 0), 'ends_at' => $day->addDays(20)->setTime(11, 0),
    ]);
    Booking::factory()->cancelled()->create([
        'resource_id' => $roomA->id,
        'starts_at' => $day->setTime(16, 0), 'ends_at' => $day->setTime(17, 0),
    ]);

    $response = $this->actingAs($viewer)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->toDateString()}")
        ->assertOk();

    $rooms = collect($response->json('rooms'));
    expect($rooms)->toHaveCount(3)
        ->and($rooms->firstWhere('id', $roomA->id)['slots'])->toHaveCount(1)
        ->and($rooms->firstWhere('id', $roomA->id)['slots'][0]['booking_id'])->toBe($mine->id)
        ->and($rooms->firstWhere('id', $roomA->id)['slots'][0]['is_mine'])->toBeTrue()
        ->and($rooms->firstWhere('id', $roomB->id)['slots'])->toHaveCount(1)
        ->and($rooms->firstWhere('id', $event->id)['is_bookable'])->toBeFalse()
        ->and($rooms->firstWhere('id', $event->id)['slots'])->toHaveCount(1);
});

it('filtre les salles demandées via rooms[]', function () {
    $viewer = User::factory()->resident()->create();
    $roomA = Resource::factory()->meetingRoom()->create();
    $roomB = Resource::factory()->meetingRoom()->create();
    $day = calendarDay();

    $response = $this->actingAs($viewer)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->toDateString()}&rooms[]={$roomA->id}")
        ->assertOk();

    expect(collect($response->json('rooms'))->pluck('id')->all())->toBe([$roomA->id])
        ->and($roomB->id)->not->toBeIn(collect($response->json('rooms'))->pluck('id')->all());
});

it('expose l’occupant d’une résa d’un autre membre (prénom, nom, entité, libellé) sans autre donnée personnelle', function () {
    // Q4 tranchée : transparence par défaut entre membres.
    $viewer = User::factory()->resident()->create();
    $company = Company::factory()->create(['legal_name' => 'Atelier Numérique']);
    $other = User::factory()->resident()->create([
        'first_name' => 'Hugo', 'last_name' => 'Discret', 'email' => 'hugo.prive@example.test',
    ]);
    // `inDirectory()` explicite : la factory tire `show_in_directory` au hasard
    // et l'opt-out masquerait le nom (cf. test dédié plus bas).
    MemberProfile::factory()->for($other)->inDirectory()->create(['company_id' => $company->id]);
    $room = Resource::factory()->meetingRoom()->create();
    $day = calendarDay();

    Booking::factory()->create([
        'user_id' => $other->id, 'resource_id' => $room->id, 'title' => 'Comité produit',
        'starts_at' => $day->setTime(9, 0), 'ends_at' => $day->setTime(10, 0),
        'status' => BookingStatus::Confirmed->value,
    ]);

    $response = $this->actingAs($viewer)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->toDateString()}")
        ->assertOk();

    $slot = $response->json('rooms.0.slots.0');
    expect($slot['is_mine'])->toBeFalse()
        ->and($slot['booking_id'])->toBeNull() // pas la résa du membre : aucun id exploitable
        ->and($slot['label'])->toBe('Comité produit')
        ->and($slot['occupant'])->toBe([
            'kind' => 'member',
            'first_name' => 'Hugo',
            'last_name' => 'Discret',
            'company_name' => 'Atelier Numérique',
        ]);

    $response->assertDontSee('hugo.prive@example.test');
});

it('affiche l’entité comme occupant d’une résa sans membre (résa admin)', function () {
    $viewer = User::factory()->resident()->create();
    $company = Company::factory()->create(['legal_name' => 'Cabinet Rhône']);
    $room = Resource::factory()->meetingRoom()->create();
    $day = calendarDay();

    Booking::factory()->create([
        'user_id' => null,
        'resource_id' => $room->id,
        'billable_type' => $company->getMorphClass(),
        'billable_id' => $company->id,
        'starts_at' => $day->setTime(9, 0), 'ends_at' => $day->setTime(10, 0),
    ]);

    $slot = $this->actingAs($viewer)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->toDateString()}")
        ->assertOk()
        ->json('rooms.0.slots.0');

    expect($slot['occupant'])->toBe([
        'kind' => 'entity',
        'first_name' => null,
        'last_name' => null,
        'company_name' => 'Cabinet Rhône',
    ]);
});

it('refuse la plage au-delà de 8 jours et les bornes incohérentes (422)', function () {
    $viewer = User::factory()->resident()->create();
    $day = calendarDay();

    $this->actingAs($viewer)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->addDays(8)->toDateString()}")
        ->assertStatus(422);

    $this->actingAs($viewer)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->subDay()->toDateString()}")
        ->assertStatus(422);

    $this->actingAs($viewer)
        ->getJson('/api/rooms/availability')
        ->assertStatus(422);
});

it('accepte une plage de 8 jours (semaine + marge)', function () {
    $viewer = User::factory()->resident()->create();
    $day = calendarDay();

    $this->actingAs($viewer)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->addDays(7)->toDateString()}")
        ->assertOk();
});

it('interdit le calendrier au contact facturation pur (403) et aux anonymes (401)', function () {
    $day = calendarDay();

    $this->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->toDateString()}")
        ->assertUnauthorized();

    $billingOnly = User::factory()->create();
    $billingOnly->assignRole(Role::BillingContact->value);

    $this->actingAs($billingOnly)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->toDateString()}")
        ->assertForbidden();
});

it('masque occupant et libellé à un external (aucune information d’identité, Q16)', function () {
    // L'external voit le calendrier pour choisir un créneau libre, mais jamais
    // l'identité des coworkers (PRD §3.5.9 — il n'a pas non plus l'annuaire).
    $external = User::factory()->external()->create();
    $company = Company::factory()->create(['legal_name' => 'Atelier Numérique']);
    $other = User::factory()->resident()->create(['first_name' => 'Hugo', 'last_name' => 'Discret']);
    MemberProfile::factory()->for($other)->inDirectory()->create(['company_id' => $company->id]);
    $room = Resource::factory()->meetingRoom()->create();
    $day = calendarDay();

    Booking::factory()->create([
        'user_id' => $other->id, 'resource_id' => $room->id, 'title' => 'Comité produit',
        'starts_at' => $day->setTime(9, 0), 'ends_at' => $day->setTime(10, 0),
    ]);

    $response = $this->actingAs($external)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->toDateString()}")
        ->assertOk();

    $slot = $response->json('rooms.0.slots.0');
    expect($slot['occupant'])->toBeNull()
        ->and($slot['label'])->toBeNull()
        ->and($slot['is_mine'])->toBeFalse();

    $response->assertDontSee('Hugo')->assertDontSee('Atelier Numérique');
});

it('laisse l’external identifier ses PROPRES réservations', function () {
    $external = User::factory()->external()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $day = calendarDay();
    $mine = Booking::factory()->create([
        'user_id' => $external->id, 'resource_id' => $room->id, 'title' => 'Mon point client',
        'starts_at' => $day->setTime(9, 0), 'ends_at' => $day->setTime(13, 0),
    ]);

    $slot = $this->actingAs($external)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->toDateString()}")
        ->assertOk()
        ->json('rooms.0.slots.0');

    expect($slot['is_mine'])->toBeTrue()
        ->and($slot['booking_id'])->toBe($mine->id)
        ->and($slot['label'])->toBe('Mon point client');
});

it('respecte l’opt-out annuaire : entité conservée, nom masqué', function () {
    $viewer = User::factory()->resident()->create();
    $company = Company::factory()->create(['legal_name' => 'Atelier Numérique']);
    $discreet = User::factory()->resident()->create([
        'first_name' => 'Hugo', 'last_name' => 'Discret',
    ]);
    MemberProfile::factory()->for($discreet)->create([
        'company_id' => $company->id,
        'show_in_directory' => false,
    ]);
    $room = Resource::factory()->meetingRoom()->create();
    $day = calendarDay();

    Booking::factory()->create([
        'user_id' => $discreet->id, 'resource_id' => $room->id, 'title' => 'Comité produit',
        'starts_at' => $day->setTime(9, 0), 'ends_at' => $day->setTime(10, 0),
    ]);

    $response = $this->actingAs($viewer)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->toDateString()}")
        ->assertOk();

    expect($response->json('rooms.0.slots.0.occupant'))->toBe([
        'kind' => 'member',
        'first_name' => null,
        'last_name' => null,
        'company_name' => 'Atelier Numérique',
    ]);

    $response->assertDontSee('Discret');
});

it('marque modifiables ses seules réservations à venir (comparaison SQL)', function () {
    $viewer = User::factory()->resident()->create();
    $room = Resource::factory()->meetingRoom()->create();
    $other = Resource::factory()->meetingRoom()->create();
    $day = CarbonImmutable::today();

    // En cours : commencée il y a 30 min — `isFuture()` en PHP la croirait à venir.
    Booking::factory()->create([
        'user_id' => $viewer->id, 'resource_id' => $room->id,
        'starts_at' => now()->subMinutes(30), 'ends_at' => now()->addMinutes(30),
    ]);
    Booking::factory()->create([
        'user_id' => $viewer->id, 'resource_id' => $other->id,
        'starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(11, 0),
    ]);

    $rooms = collect($this->actingAs($viewer)
        ->getJson("/api/rooms/availability?from={$day->toDateString()}&to={$day->addDay()->toDateString()}")
        ->assertOk()
        ->json('rooms'));

    expect($rooms->firstWhere('id', $room->id)['slots'][0]['cancellable'])->toBeFalse()
        ->and($rooms->firstWhere('id', $room->id)['slots'][0]['is_mine'])->toBeTrue()
        ->and($rooms->firstWhere('id', $other->id)['slots'][0]['cancellable'])->toBeTrue();
});
