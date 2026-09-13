<?php

declare(strict_types=1);

use App\Enums\Audience;
use App\Models\InternalDocument;
use App\Models\User;
use App\Notifications\InternalDocumentPublishedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Lot G — « nouveau document interne à valider » (PRD §3.8.4). Événement
 * critique : in-app + email selon les préférences du membre. Émis à la
 * publication et à chaque nouvelle version (re-validation requise,
 * data_model §4.5), pour la seule audience du document.
 */

// ── Document interne à valider ───────────────────────────────────────────────

it('notifie l\'audience à la publication d\'un document interne (in-app + email)', function () {
    Notification::fake();

    $resident = User::factory()->resident()->create();
    $additional = User::factory()->additional()->create();

    InternalDocument::factory()->create([
        'audience' => Audience::All->value,
        'published_at' => now(),
    ]);

    foreach ([$resident, $additional] as $member) {
        Notification::assertSentTo($member, InternalDocumentPublishedNotification::class, function ($notification, array $channels) {
            return in_array('database', $channels, true) && in_array('mail', $channels, true);
        });
    }
});

it('ne notifie que les membres couverts par l\'audience du document', function () {
    Notification::fake();

    $resident = User::factory()->resident()->create();
    $additional = User::factory()->additional()->create();

    InternalDocument::factory()->create([
        'audience' => Audience::Residents->value,
        'published_at' => now(),
    ]);

    Notification::assertSentTo($resident, InternalDocumentPublishedNotification::class);
    Notification::assertNotSentTo($additional, InternalDocumentPublishedNotification::class);
});

it('ne notifie ni les comptes désactivés ni les comptes anonymisés', function () {
    Notification::fake();

    $active = User::factory()->resident()->create();
    $deactivated = User::factory()->resident()->create();
    $deactivated->delete();
    $anonymized = User::factory()->resident()->create(['anonymized_at' => now()]);

    InternalDocument::factory()->create(['published_at' => now()]);

    Notification::assertSentTo($active, InternalDocumentPublishedNotification::class);
    Notification::assertNotSentTo($deactivated, InternalDocumentPublishedNotification::class);
    Notification::assertNotSentTo($anonymized, InternalDocumentPublishedNotification::class);
});

it('ne notifie pas un document non publié, puis notifie à sa publication', function () {
    Notification::fake();

    $resident = User::factory()->resident()->create();

    $document = InternalDocument::factory()->create(['published_at' => null]);
    Notification::assertNothingSent();

    $document->update(['published_at' => now()]);

    Notification::assertSentTo($resident, InternalDocumentPublishedNotification::class);
});

it('renotifie l\'audience à la publication d\'une nouvelle version', function () {
    Notification::fake();

    $resident = User::factory()->resident()->create();
    $document = InternalDocument::factory()->create(['version' => '1.0', 'published_at' => now()]);

    $document->update(['version' => '2.0']);

    Notification::assertSentToTimes($resident, InternalDocumentPublishedNotification::class, 2);
});

it('ne renotifie pas une simple correction de libellé', function () {
    Notification::fake();

    $resident = User::factory()->resident()->create();
    $document = InternalDocument::factory()->create(['published_at' => now()]);

    $document->update(['title' => 'Charte du coworking (corrigée)']);

    Notification::assertSentToTimes($resident, InternalDocumentPublishedNotification::class, 1);
});

it('respecte le toggle email coupé sur le document à valider (in-app seul)', function () {
    Notification::fake();

    $resident = User::factory()->resident()->create(['notify_email' => false]);

    InternalDocument::factory()->create(['published_at' => now()]);

    Notification::assertSentTo($resident, InternalDocumentPublishedNotification::class, function ($notification, array $channels) {
        return $channels === ['database'];
    });
});

it('fige titre et version à la construction (review — piège de sérialisation en queue)', function () {
    $resident = User::factory()->resident()->create();
    $document = InternalDocument::factory()->create(['title' => 'Charte', 'version' => '1.0']);

    // Notification v1 « en file » : construite avant la republication.
    $v1 = new InternalDocumentPublishedNotification($document);

    // Republication rapprochée : la ligne en base passe en v2 pendant que la
    // notification v1 est encore en queue (SerializesModels ne réhydrate
    // qu'un identifiant — sans figeage, `toDatabase()` relirait la v2 pour
    // LES DEUX notifications).
    $document->update(['title' => 'Charte (corrigée)', 'version' => '2.0']);
    $v2 = new InternalDocumentPublishedNotification($document->fresh());

    $payloadV1 = $v1->toDatabase($resident);
    $payloadV2 = $v2->toDatabase($resident);

    expect($payloadV1['title'])->toBe('Charte')
        ->and($payloadV1['version'])->toBe('1.0')
        ->and($payloadV2['title'])->toBe('Charte (corrigée)')
        ->and($payloadV2['version'])->toBe('2.0');
});
