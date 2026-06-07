<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Company;
use App\Models\MemberProfile;
use App\Models\User;

/**
 * C9.2 — Flux iCal d'abonnement (PRD §3.5.8). Authentification par token en URL
 * (capacité), périmètre scopé, isolation entre membres / entités.
 */
it('génère un flux iCal des réservations du membre (token en URL)', function () {
    $user = User::factory()->create();
    $token = $user->ensureCalendarToken();
    $booking = Booking::factory()->for($user)->create(['title' => 'Comité']);

    $response = $this->get("/calendar/{$token}/mine.ics");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    expect($response->getContent())
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('BEGIN:VEVENT')
        ->toContain("UID:booking-{$booking->id}@ecoworking.fr")
        ->toContain('END:VCALENDAR');
});

it('n\'inclut pas les réservations d\'un autre membre dans « mes réservations »', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = $user->ensureCalendarToken();

    $mine = Booking::factory()->for($user)->create();
    $theirs = Booking::factory()->for($other)->create();

    $content = $this->get("/calendar/{$token}/mine.ics")->getContent();

    expect($content)
        ->toContain("booking-{$mine->id}@")
        ->not->toContain("booking-{$theirs->id}@");
});

it('exclut les réservations annulées du flux', function () {
    $user = User::factory()->create();
    $token = $user->ensureCalendarToken();
    $cancelled = Booking::factory()->for($user)->cancelled()->create();

    $content = $this->get("/calendar/{$token}/mine.ics")->getContent();

    expect($content)->not->toContain("booking-{$cancelled->id}@");
});

it('agrège les réservations des membres de la même entité dans le flux entité', function () {
    $company = Company::factory()->create();
    $me = User::factory()->create();
    $colleague = User::factory()->create();
    MemberProfile::factory()->for($me)->create(['company_id' => $company->id]);
    MemberProfile::factory()->for($colleague)->create(['company_id' => $company->id]);
    $token = $me->ensureCalendarToken();

    $mine = Booking::factory()->for($me)->create();
    $colleagues = Booking::factory()->for($colleague)->create();

    $content = $this->get("/calendar/{$token}/entity.ics")->getContent();

    expect($content)
        ->toContain("booking-{$mine->id}@")
        ->toContain("booking-{$colleagues->id}@");
});

it('isole le flux entité d\'une autre entité', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $me = User::factory()->create();
    $stranger = User::factory()->create();
    MemberProfile::factory()->for($me)->create(['company_id' => $companyA->id]);
    MemberProfile::factory()->for($stranger)->create(['company_id' => $companyB->id]);
    $token = $me->ensureCalendarToken();

    $strangerBooking = Booking::factory()->for($stranger)->create();

    $content = $this->get("/calendar/{$token}/entity.ics")->getContent();

    expect($content)->not->toContain("booking-{$strangerBooking->id}@");
});

it('renvoie 404 sur un token inconnu (pas d\'énumération)', function () {
    $this->get('/calendar/'.str_repeat('a', 48).'/mine.ics')->assertNotFound();
});

// ── Gestion du token (API authentifiée) ─────────────────────────────────────

it('expose les URLs d\'abonnement au membre authentifié', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/calendar')
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonStructure(['enabled', 'urls' => ['mine', 'entity']]);

    expect($user->fresh()->calendar_token)->not->toBeNull();
});

it('régénère le token et révoque l\'ancien abonnement', function () {
    $user = User::factory()->create();
    $old = $user->ensureCalendarToken();

    $this->actingAs($user)->postJson('/api/calendar/token')->assertOk();

    $new = $user->fresh()->calendar_token;
    expect($new)->not->toBe($old);

    // L'ancien token ne résout plus.
    $this->get("/calendar/{$old}/mine.ics")->assertNotFound();
    $this->get("/calendar/{$new}/mine.ics")->assertOk();
});

it('révoque l\'abonnement iCal', function () {
    $user = User::factory()->create();
    $token = $user->ensureCalendarToken();

    $this->actingAs($user)->deleteJson('/api/calendar/token')
        ->assertOk()
        ->assertJsonPath('enabled', false);

    $this->get("/calendar/{$token}/mine.ics")->assertNotFound();
});

it('exige l\'authentification pour gérer l\'abonnement', function () {
    $this->getJson('/api/calendar')->assertUnauthorized();
});
