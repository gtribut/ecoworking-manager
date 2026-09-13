<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\Period;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\MemberProfile;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\User;
use App\Notifications\AbsenceDeclaredNotification;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Services\IssueInvoiceService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * C8 — Notifications & emails. Couvre le routage des canaux selon les
 * préférences (PRD §3.8.4), le déclenchement sur les événements métier
 * (facture émise/retard, absence déclarée) et le centre de notifications in-app.
 */

/** Contact facturation rattaché à $company, avec préférences notif au besoin. */
function billingRecipientFor(Company $company, bool $email = true, bool $inApp = true): User
{
    $user = User::factory()->billingContact()->create([
        'notify_email' => $email,
        'notify_in_app' => $inApp,
    ]);
    Contact::factory()->billing()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
    ]);

    return $user;
}

/** Brouillon avec une ligne, prêt à être émis. */
function draftInvoiceFor(Company $company): Invoice
{
    $invoice = Invoice::factory()->create([
        'billable_type' => 'company',
        'billable_id' => $company->id,
        'status' => InvoiceStatus::Draft->value,
        'number' => null,
    ]);

    InvoiceLine::factory()->for($invoice)->create([
        'description' => 'Abonnement résident',
        'quantity' => 1,
        'unit_price_ht' => 200.00,
        'vat_rate' => 20.00,
        'line_total_ht' => 200.00,
        'line_vat' => 40.00,
        'line_total_ttc' => 240.00,
    ]);

    return $invoice->fresh();
}

// ── C8.1 — Déclenchement & routage des canaux ───────────────────────────────

it('notifie le contact facturation à l\'émission d\'une facture (in-app + email)', function () {
    Notification::fake();
    Storage::fake('local');

    $company = Company::factory()->create();
    $recipient = billingRecipientFor($company);
    $invoice = draftInvoiceFor($company);

    app(IssueInvoiceService::class)->issue($invoice);

    Notification::assertSentTo($recipient, InvoiceIssuedNotification::class, function ($notification, array $channels) {
        return in_array('database', $channels, true) && in_array('mail', $channels, true);
    });
});

it('respecte le toggle email coupé (in-app seulement)', function () {
    Notification::fake();
    Storage::fake('local');

    $company = Company::factory()->create();
    $recipient = billingRecipientFor($company, email: false, inApp: true);
    $invoice = draftInvoiceFor($company);

    app(IssueInvoiceService::class)->issue($invoice);

    Notification::assertSentTo($recipient, InvoiceIssuedNotification::class, function ($notification, array $channels) {
        return $channels === ['database'];
    });
});

it('respecte le toggle in-app coupé (email seulement)', function () {
    Notification::fake();
    Storage::fake('local');

    $company = Company::factory()->create();
    $recipient = billingRecipientFor($company, email: true, inApp: false);
    $invoice = draftInvoiceFor($company);

    app(IssueInvoiceService::class)->issue($invoice);

    Notification::assertSentTo($recipient, InvoiceIssuedNotification::class, function ($notification, array $channels) {
        return $channels === ['mail'];
    });
});

it('ne notifie sur aucun canal si le membre a tout coupé', function () {
    Notification::fake();
    Storage::fake('local');

    $company = Company::factory()->create();
    $recipient = billingRecipientFor($company, email: false, inApp: false);
    $invoice = draftInvoiceFor($company);

    app(IssueInvoiceService::class)->issue($invoice);

    Notification::assertNotSentTo($recipient, InvoiceIssuedNotification::class);
});

it('notifie le retard de paiement et bascule le statut', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $recipient = billingRecipientFor($company);

    $invoice = Invoice::factory()->issued()->create([
        'billable_type' => 'company',
        'billable_id' => $company->id,
        'due_at' => now()->subDays(3)->toDateString(),
        'total_ttc' => 240.00,
        'amount_paid' => 0,
    ]);

    $this->artisan('invoices:update-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);
    Notification::assertSentTo($recipient, InvoiceOverdueNotification::class);
});

it('ne renotifie pas le retard après un paiement partiel (review F7)', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $recipient = billingRecipientFor($company);

    $invoice = Invoice::factory()->issued()->create([
        'billable_type' => 'company',
        'billable_id' => $company->id,
        'due_at' => now()->subDays(3)->toDateString(),
        'total_ttc' => 240.00,
        'amount_paid' => 0,
    ]);

    $this->artisan('invoices:update-overdue')->assertSuccessful();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);

    // Paiement partiel : la facture reste échue et non soldée → toujours en retard…
    Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 40]);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);

    // … et les passages suivants du cron ne redéclenchent PAS de notification.
    $this->artisan('invoices:update-overdue')->assertSuccessful();
    $this->artisan('invoices:update-overdue')->assertSuccessful();

    Notification::assertSentToTimes($recipient, InvoiceOverdueNotification::class, 1);
});

it('notifie les admins (in-app uniquement) quand un résident déclare une absence', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $resident = User::factory()->resident()->create();
    $desk = Resource::factory()->desk()->create();
    MemberProfile::factory()->for($resident)->create(['desk_id' => $desk->id]);

    $this->actingAs($resident)->postJson('/api/absences', [
        'date_start' => now()->addDay()->toDateString(),
        'period' => Period::FullDay->value,
    ])->assertCreated();

    Notification::assertSentTo($admin, AbsenceDeclaredNotification::class, function ($notification, array $channels) {
        return $channels === ['database']; // non critique → jamais d'email
    });
});

// ── C8.2 — Centre de notifications in-app (API auto-scopée) ──────────────────

it('liste les notifications du membre avec compteur de non-lues', function () {
    $user = User::factory()->create();
    $user->notify(new InvoiceOverdueNotification(Invoice::factory()->issued()->create()));
    $user->notify(new InvoiceOverdueNotification(Invoice::factory()->issued()->create()));

    $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.unread_count'))->toBe(2);
});

it('marque une notification comme lue', function () {
    $user = User::factory()->create();
    $user->notify(new InvoiceOverdueNotification(Invoice::factory()->issued()->create()));
    $id = $user->notifications()->first()->id;

    $this->actingAs($user)->postJson("/api/notifications/{$id}/read")->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('marque toutes les notifications comme lues', function () {
    $user = User::factory()->create();
    $user->notify(new InvoiceOverdueNotification(Invoice::factory()->issued()->create()));
    $user->notify(new InvoiceOverdueNotification(Invoice::factory()->issued()->create()));

    $this->actingAs($user)->postJson('/api/notifications/read-all')->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('isole les notifications entre membres (A ne lit pas celles de B)', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userB->notify(new InvoiceOverdueNotification(Invoice::factory()->issued()->create()));
    $bId = $userB->notifications()->first()->id;

    // A ne voit aucune notification…
    $this->actingAs($userA)->getJson('/api/notifications')
        ->assertOk()->assertJsonCount(0, 'data');

    // …et ne peut pas marquer celle de B comme lue.
    $this->actingAs($userA)->postJson("/api/notifications/{$bId}/read")->assertNotFound();
});

it('exige l\'authentification pour le centre de notifications', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});

// ── C8.3 — Préférences de notification ───────────────────────────────────────

it('persiste les toggles de notification via le profil', function () {
    $user = User::factory()->create(['notify_email' => true, 'notify_in_app' => true]);

    $this->actingAs($user)->patchJson('/api/profile', [
        'notify_email' => false,
        'notify_in_app' => true,
    ])->assertOk()
        ->assertJsonPath('user.notify_email', false)
        ->assertJsonPath('user.notify_in_app', true);

    expect($user->fresh()->notify_email)->toBeFalse();
});

// ── Lot G — pagination de la cloche (PRD §3.8.4, « Charger plus ») ───────────

it('pagine la liste des notifications sans fausser le badge de non-lues', function () {
    $user = User::factory()->create();

    foreach (range(1, 3) as $i) {
        $user->notify(new InvoiceOverdueNotification(Invoice::factory()->issued()->create()));
    }

    $first = $this->actingAs($user)->getJson('/api/notifications?per_page=2')->assertOk();

    expect($first->json('data'))->toHaveCount(2)
        ->and($first->json('meta.current_page'))->toBe(1)
        ->and($first->json('meta.last_page'))->toBe(2)
        ->and($first->json('meta.per_page'))->toBe(2)
        ->and($first->json('meta.total'))->toBe(3)
        // Le badge compte TOUTES les non-lues, pas seulement la page courante.
        ->and($first->json('meta.unread_count'))->toBe(3);

    $second = $this->actingAs($user)->getJson('/api/notifications?per_page=2&page=2')->assertOk();

    expect($second->json('data'))->toHaveCount(1)
        ->and($second->json('meta.current_page'))->toBe(2);
});

it('borne la taille de page demandée par le client', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/notifications?per_page=5000')->assertStatus(422);
    $this->actingAs($user)->getJson('/api/notifications?page=0')->assertStatus(422);
});
