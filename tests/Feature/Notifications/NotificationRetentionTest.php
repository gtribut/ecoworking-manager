<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceOverdueNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

/**
 * Lot G — rétention de l'historique des notifications (PRD §3.8.4,
 * « historique conservé 90 j configurable »). La purge supprime TOUTES les
 * notifications trop anciennes, lues comme non lues : le PRD parle d'un
 * historique borné, pas d'un nettoyage des seules lignes lues.
 */

/** Notification in-app du membre, antidatée de $days jours. */
function agedNotification(User $user, int $days, bool $read = false): DatabaseNotification
{
    $user->notify(new InvoiceOverdueNotification(Invoice::factory()->issued()->create()));

    /** @var DatabaseNotification $notification */
    $notification = $user->notifications()->latest()->first();

    DB::table('notifications')->where('id', $notification->id)->update([
        'created_at' => now()->subDays($days),
        'read_at' => $read ? now()->subDays($days) : null,
    ]);

    return $notification;
}

it('purge les notifications au-delà de la rétention et garde les récentes', function () {
    $user = User::factory()->create();

    $old = agedNotification($user, 91);
    $oldRead = agedNotification($user, 120, read: true);
    $recent = agedNotification($user, 89);

    $this->artisan('notifications:purge')->assertSuccessful();

    expect(DatabaseNotification::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(DatabaseNotification::query()->whereKey($oldRead->id)->exists())->toBeFalse()
        ->and(DatabaseNotification::query()->whereKey($recent->id)->exists())->toBeTrue();
});

it('respecte la rétention configurée', function () {
    config()->set('notifications.retention_days', 30);

    $user = User::factory()->create();
    $beyond = agedNotification($user, 31);
    $within = agedNotification($user, 29);

    $this->artisan('notifications:purge')->assertSuccessful();

    expect(DatabaseNotification::query()->whereKey($beyond->id)->exists())->toBeFalse()
        ->and(DatabaseNotification::query()->whereKey($within->id)->exists())->toBeTrue();
});

it('accepte une rétention ponctuelle en option de commande', function () {
    $user = User::factory()->create();
    $notification = agedNotification($user, 10);

    $this->artisan('notifications:purge --days=5')->assertSuccessful();

    expect(DatabaseNotification::query()->whereKey($notification->id)->exists())->toBeFalse();
});

it('rejette une option --days invalide sans rien supprimer (review)', function (string $invalid) {
    $user = User::factory()->create();
    $notification = agedNotification($user, 200);

    $this->artisan("notifications:purge --days={$invalid}")->assertFailed();

    // `(int) '' === 0` bornait auparavant silencieusement à 1 jour (purge
    // quasi totale) : on vérifie qu'aucune ligne n'a été supprimée.
    expect(DatabaseNotification::query()->whereKey($notification->id)->exists())->toBeTrue();
})->with([
    'vide' => [''],
    'non numérique' => ['abc'],
    'zéro' => ['0'],
    'négatif' => ['-5'],
]);

it('planifie la purge quotidiennement', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'notifications:purge'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 4 * * *');
});
