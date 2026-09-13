<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use App\Notifications\BookingChangedNotification;
use App\Services\BookingService;
use Illuminate\Support\Facades\Notification;

/**
 * Lot G — « réservation créée / modifiée / annulée par l'admin » (PRD §3.8.4).
 * In-app seul (non critique). La notification ne part que si l'acteur
 * authentifié diffère du propriétaire ; sans acteur (console, queue, seeder),
 * rien n'est envoyé.
 */

/** Réservation de salle créée par $actor pour $owner (chemin back-office). */
function adminBookingFor(User $owner, User $actor): Booking
{
    test()->actingAs($actor);

    return app(BookingService::class)->create([
        'resource' => Resource::factory()->meetingRoom()->create(),
        'user' => $owner,
        'starts_at' => now()->addWeek()->startOfHour(),
        'ends_at' => now()->addWeek()->startOfHour()->addHour(),
        'created_by' => $actor->id,
    ]);
}

// ── Réservation créée / modifiée / annulée par l'admin ───────────────────────

it('notifie le membre quand l\'admin crée une réservation pour lui', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $member = User::factory()->resident()->create();

    adminBookingFor($member, $admin);

    Notification::assertSentTo($member, BookingChangedNotification::class, function ($notification, array $channels) {
        return $channels === ['database']; // non critique → jamais d'email
    });
});

it('ne notifie pas le membre qui réserve lui-même', function () {
    Notification::fake();

    $member = User::factory()->resident()->create();

    adminBookingFor($member, $member);

    Notification::assertNothingSent();
});

it('notifie la modification puis l\'annulation d\'une réservation par l\'admin', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->resident()->create();
    $booking = adminBookingFor($member, $admin);

    Notification::fake();

    $this->actingAs($admin);
    app(BookingService::class)->update($booking, [
        'starts_at' => now()->addWeeks(2)->startOfHour(),
        'ends_at' => now()->addWeeks(2)->startOfHour()->addHour(),
    ]);
    app(BookingService::class)->cancel($booking->fresh(), 'Salle indisponible');

    Notification::assertSentToTimes($member, BookingChangedNotification::class, 2);
});

it('notifie la suppression d\'une réservation par l\'admin', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->resident()->create();
    $booking = adminBookingFor($member, $admin);

    Notification::fake();

    $this->actingAs($admin);
    $booking->delete();

    Notification::assertSentTo($member, BookingChangedNotification::class);
});

it('ne notifie aucune réservation hors contexte authentifié (seeder, console, queue)', function () {
    Notification::fake();

    $member = User::factory()->resident()->create();

    Booking::factory()->create(['user_id' => $member->id]);

    Notification::assertNothingSent();
});
