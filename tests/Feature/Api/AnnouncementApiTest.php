<?php

declare(strict_types=1);

use App\Enums\AnnouncementRegistrationStatus;
use App\Enums\AnnouncementStatus;
use App\Enums\Audience;
use App\Models\Announcement;
use App\Models\AnnouncementRegistration;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * C12.3 — API portail annonces & événements : scope de lecture
 * (publiée + audience, PRD §3.3.2 / §4.11), RSVP (PRD §2.5) avec Policy
 * AnnouncementRegistrationPolicy exercée, isolation A/B, notification de
 * publication (PRD §3.8.4).
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class); // câble rôles → permissions
});

/** Événement publié, visible de tous, ouvert aux inscriptions. */
function publishedEvent(array $attributes = []): Announcement
{
    return Announcement::factory()->event()->published()->create($attributes);
}

// --- Auth ------------------------------------------------------------------

it('protège les endpoints annonces : 401 si non authentifié', function () {
    $this->getJson('/api/announcements')->assertUnauthorized();
    $this->getJson('/api/announcements/1')->assertUnauthorized();
    $this->postJson('/api/announcements/1/registration')->assertUnauthorized();
    $this->deleteJson('/api/announcements/1/registration')->assertUnauthorized();
});

// --- Scope de lecture (statut) ----------------------------------------------

it('ne liste que les annonces publiées (brouillons et archivées invisibles)', function () {
    Notification::fake();
    $published = Announcement::factory()->published()->create();
    $draft = Announcement::factory()->create();
    $archived = Announcement::factory()->create(['status' => AnnouncementStatus::Archived->value]);

    $response = $this->actingAs(User::factory()->resident()->create())
        ->getJson('/api/announcements')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain($published->id)
        ->not->toContain($draft->id)
        ->not->toContain($archived->id);
});

it('répond 404 sur le détail d\'un brouillon (indistinguable d\'une annonce inexistante)', function () {
    $draft = Announcement::factory()->create();

    $this->actingAs(User::factory()->resident()->create())
        ->getJson("/api/announcements/{$draft->id}")
        ->assertNotFound();
});

// --- Scope de lecture (audience) ---------------------------------------------

it('scope la liste par audience selon les rôles', function () {
    Notification::fake();
    $all = Announcement::factory()->published()->create(['visibility' => Audience::All->value]);
    $residents = Announcement::factory()->published()->create(['visibility' => Audience::Residents->value]);
    $additional = Announcement::factory()->published()->create(['visibility' => Audience::Additional->value]);
    $billing = Announcement::factory()->published()->create(['visibility' => Audience::BillingContact->value]);

    $idsFor = function (User $user): array {
        return collect($this->actingAs($user)->getJson('/api/announcements')->json('data'))
            ->pluck('id')->all();
    };

    // Résident : all + residents, pas additional ni billing_contact.
    expect($idsFor(User::factory()->resident()->create()))
        ->toContain($all->id, $residents->id)
        ->not->toContain($additional->id, $billing->id);

    // Additional : all + additional.
    expect($idsFor(User::factory()->additional()->create()))
        ->toContain($all->id, $additional->id)
        ->not->toContain($residents->id, $billing->id);

    // External : uniquement all.
    expect($idsFor(User::factory()->external()->create()))
        ->toContain($all->id)
        ->not->toContain($residents->id, $additional->id, $billing->id);

    // Staff : équivalent résident côté portail (PRD §2.5).
    expect($idsFor(User::factory()->staff()->create()))
        ->toContain($all->id, $residents->id)
        ->not->toContain($additional->id, $billing->id);

    // Billing contact pur : all + billing_contact.
    expect($idsFor(User::factory()->billingContact()->create()))
        ->toContain($all->id, $billing->id)
        ->not->toContain($residents->id, $additional->id);

    // Admin : tout ce qui est publié.
    expect($idsFor(User::factory()->admin()->create()))
        ->toContain($all->id, $residents->id, $additional->id, $billing->id);
});

it('répond 404 sur le détail d\'une annonce hors audience', function () {
    Notification::fake();
    $residentsOnly = Announcement::factory()->published()->create(['visibility' => Audience::Residents->value]);

    $this->actingAs(User::factory()->additional()->create())
        ->getJson("/api/announcements/{$residentsOnly->id}")
        ->assertNotFound();
});

it('expose le statut d\'inscription du membre et la jauge sur la liste', function () {
    Notification::fake();
    $user = User::factory()->resident()->create();
    $event = publishedEvent(['max_participants' => 10]);
    AnnouncementRegistration::factory()->create(['announcement_id' => $event->id, 'user_id' => $user->id]);
    AnnouncementRegistration::factory()->create(['announcement_id' => $event->id]); // un autre inscrit

    $response = $this->actingAs($user)->getJson('/api/announcements')->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $event->id);

    expect($row['is_registered'])->toBeTrue()
        ->and($row['my_registration_status'])->toBe(AnnouncementRegistrationStatus::Registered->value)
        ->and($row['registered_count'])->toBe(2)
        ->and($row['spots_left'])->toBe(8)
        ->and($row['is_registrable'])->toBeTrue();
});

// --- RSVP : inscription -------------------------------------------------------

it('permet à un membre de s\'inscrire à un événement', function () {
    Notification::fake();
    $user = User::factory()->resident()->create();
    $event = publishedEvent();

    $this->actingAs($user)
        ->postJson("/api/announcements/{$event->id}/registration")
        ->assertCreated();

    expect($event->registrations()->where('user_id', $user->id)->firstOrFail()->status)
        ->toBe(AnnouncementRegistrationStatus::Registered);
});

it('refuse l\'inscription à une annonce qui n\'est pas un événement (422)', function () {
    Notification::fake();
    $info = Announcement::factory()->published()->create();

    $this->actingAs(User::factory()->resident()->create())
        ->postJson("/api/announcements/{$info->id}/registration")
        ->assertUnprocessable();
});

it('refuse l\'inscription à un événement sans inscription requise (422)', function () {
    Notification::fake();
    $event = publishedEvent(['requires_registration' => false]);

    $this->actingAs(User::factory()->resident()->create())
        ->postJson("/api/announcements/{$event->id}/registration")
        ->assertUnprocessable();
});

it('refuse l\'inscription à un événement passé (422)', function () {
    Notification::fake();
    $event = publishedEvent([
        'event_starts_at' => now()->subDay(),
        'event_ends_at' => now()->subDay()->addHours(2),
    ]);

    $this->actingAs(User::factory()->resident()->create())
        ->postJson("/api/announcements/{$event->id}/registration")
        ->assertUnprocessable();
});

it('refuse l\'inscription quand l\'événement est complet (422)', function () {
    Notification::fake();
    $event = publishedEvent(['max_participants' => 1]);
    AnnouncementRegistration::factory()->create(['announcement_id' => $event->id]);

    $this->actingAs(User::factory()->resident()->create())
        ->postJson("/api/announcements/{$event->id}/registration")
        ->assertUnprocessable();
});

it('ne compte pas les inscriptions annulées dans la jauge', function () {
    Notification::fake();
    $event = publishedEvent(['max_participants' => 1]);
    AnnouncementRegistration::factory()->create([
        'announcement_id' => $event->id,
        'status' => AnnouncementRegistrationStatus::Cancelled->value,
    ]);

    $this->actingAs(User::factory()->resident()->create())
        ->postJson("/api/announcements/{$event->id}/registration")
        ->assertCreated();
});

it('refuse une double inscription (422) sans créer de doublon', function () {
    Notification::fake();
    $user = User::factory()->resident()->create();
    $event = publishedEvent();
    AnnouncementRegistration::factory()->create(['announcement_id' => $event->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/api/announcements/{$event->id}/registration")
        ->assertUnprocessable();

    expect($event->registrations()->where('user_id', $user->id)->count())->toBe(1);
});

it('réactive la même ligne lors d\'une ré-inscription après annulation', function () {
    Notification::fake();
    $user = User::factory()->resident()->create();
    $event = publishedEvent();
    $registration = AnnouncementRegistration::factory()->create([
        'announcement_id' => $event->id,
        'user_id' => $user->id,
        'status' => AnnouncementRegistrationStatus::Cancelled->value,
    ]);

    $this->actingAs($user)
        ->postJson("/api/announcements/{$event->id}/registration")
        ->assertCreated();

    expect($event->registrations()->where('user_id', $user->id)->count())->toBe(1)
        ->and($registration->fresh()->status)->toBe(AnnouncementRegistrationStatus::Registered);
});

it('répond 404 à l\'inscription sur un événement hors audience (pas de fuite)', function () {
    Notification::fake();
    $event = publishedEvent(['visibility' => Audience::Residents->value]);

    $this->actingAs(User::factory()->additional()->create())
        ->postJson("/api/announcements/{$event->id}/registration")
        ->assertNotFound();

    expect($event->registrations()->count())->toBe(0);
});

it('refuse l\'inscription à un billing_contact pur — Policy create (403)', function () {
    Notification::fake();
    // Événement visible de tous : le refus vient bien de la permission
    // register-event (AnnouncementRegistrationPolicy::create), pas de l'audience.
    $event = publishedEvent();

    $this->actingAs(User::factory()->billingContact()->create())
        ->postJson("/api/announcements/{$event->id}/registration")
        ->assertForbidden();
});

// --- RSVP : désinscription ----------------------------------------------------

it('permet à un membre d\'annuler son inscription (ligne conservée en cancelled)', function () {
    Notification::fake();
    $user = User::factory()->resident()->create();
    $event = publishedEvent();
    $registration = AnnouncementRegistration::factory()->create([
        'announcement_id' => $event->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/announcements/{$event->id}/registration")
        ->assertOk();

    expect($registration->fresh()->status)->toBe(AnnouncementRegistrationStatus::Cancelled)
        ->and($event->registrations()->count())->toBe(1);
});

it('répond 404 à la désinscription sans inscription active', function () {
    Notification::fake();
    $event = publishedEvent();

    $this->actingAs(User::factory()->resident()->create())
        ->deleteJson("/api/announcements/{$event->id}/registration")
        ->assertNotFound();
});

it('refuse la désinscription d\'un événement déjà commencé (422)', function () {
    Notification::fake();
    $user = User::factory()->resident()->create();
    $event = publishedEvent();
    AnnouncementRegistration::factory()->create(['announcement_id' => $event->id, 'user_id' => $user->id]);
    // subDay (pas subHour) : l'écriture datetime est naïve en tz applicative et
    // relue en UTC (comportement projet) — un petit décalage repasserait « futur ».
    $event->update(['event_starts_at' => now()->subDay()]);

    $this->actingAs($user)
        ->deleteJson("/api/announcements/{$event->id}/registration")
        ->assertUnprocessable();
});

it('isole les inscriptions : la désinscription de A ne touche jamais celle de B', function () {
    Notification::fake();
    $memberA = User::factory()->resident()->create();
    $memberB = User::factory()->resident()->create();
    $event = publishedEvent();
    $registrationB = AnnouncementRegistration::factory()->create([
        'announcement_id' => $event->id,
        'user_id' => $memberB->id,
    ]);

    // A n'est pas inscrit : sa désinscription échoue (404) et B reste inscrit —
    // la route ne porte aucun id d'inscription, impossible de viser celle de B.
    $this->actingAs($memberA)
        ->deleteJson("/api/announcements/{$event->id}/registration")
        ->assertNotFound();

    expect($registrationB->fresh()->status)->toBe(AnnouncementRegistrationStatus::Registered);

    // A inscrit puis désinscrit : seule SA ligne passe en cancelled.
    AnnouncementRegistration::factory()->create(['announcement_id' => $event->id, 'user_id' => $memberA->id]);
    $this->actingAs($memberA)
        ->deleteJson("/api/announcements/{$event->id}/registration")
        ->assertOk();

    expect($registrationB->fresh()->status)->toBe(AnnouncementRegistrationStatus::Registered);
});

// --- Notification de publication (PRD §3.8.4) ---------------------------------

it('notifie l\'audience in-app à la publication (draft → published)', function () {
    Notification::fake();
    $resident = User::factory()->resident()->create();
    $additional = User::factory()->additional()->create();
    $author = User::factory()->admin()->create();

    $announcement = Announcement::factory()->create([
        'visibility' => Audience::Residents->value,
        'created_by' => $author->id,
    ]);
    Notification::assertNothingSent(); // brouillon : personne n'est notifié

    $announcement->update(['status' => AnnouncementStatus::Published->value, 'published_at' => now()]);

    Notification::assertSentTo($resident, AnnouncementPublishedNotification::class);
    Notification::assertNotSentTo($additional, AnnouncementPublishedNotification::class);
    Notification::assertNotSentTo($author, AnnouncementPublishedNotification::class);
});

it('ne re-notifie pas lors d\'une simple édition d\'une annonce déjà publiée', function () {
    $resident = User::factory()->resident()->create();
    $announcement = Announcement::factory()->published()->create();

    Notification::fake();
    $announcement->update(['title' => 'Titre corrigé']);

    Notification::assertNothingSent();
});

it('la notification de publication est in-app uniquement (pas d\'email)', function () {
    $resident = User::factory()->resident()->create();

    Notification::fake();
    Announcement::factory()->published()->create();

    Notification::assertSentTo(
        $resident,
        AnnouncementPublishedNotification::class,
        fn (AnnouncementPublishedNotification $notification, array $channels): bool => $channels === ['database'],
    );
});
