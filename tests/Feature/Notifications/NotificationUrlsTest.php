<?php

declare(strict_types=1);

use App\Filament\Resources\DeskAbsences\DeskAbsenceResource;
use App\Models\DeskAbsence;
use App\Models\InternalDocument;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\AbsenceDeclaredNotification;
use App\Notifications\InternalDocumentPublishedNotification;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\InvoiceOverdueNotification;

/**
 * Liens des notifications existantes vers de vraies routes (fix review) :
 * les factures pointaient `/factures`, une route qui n'existe pas côté
 * portail (App.tsx déclare `/invoices`) ; l'absence déclarée pointait
 * `/admin`, qui n'existe pas non plus côté portail — cette notification est
 * destinée à un admin, pas à un membre.
 */
it('pointe les notifications de facture vers /invoices, pas /factures', function () {
    $recipient = User::factory()->create();
    $invoice = Invoice::factory()->issued()->create();

    $issued = (new InvoiceIssuedNotification($invoice))->toDatabase($recipient);
    $overdue = (new InvoiceOverdueNotification($invoice))->toDatabase($recipient);

    expect($issued['url'])->toBe('/invoices')
        ->and($overdue['url'])->toBe('/invoices');
});

it('pointe l’absence déclarée vers le back-office, jamais /admin (route SPA inexistante)', function () {
    $admin = User::factory()->admin()->create();
    $resident = User::factory()->resident()->create();
    $absence = DeskAbsence::factory()->create(['user_id' => $resident->id]);

    $payload = (new AbsenceDeclaredNotification($absence, $resident))->toDatabase($admin);

    expect($payload['url'])->not->toBe('/admin');

    // Résolu (contexte de test, ADMIN_DOMAIN vide) ou `null` à défaut — jamais
    // une route inexistante côté portail.
    if ($payload['url'] !== null) {
        expect($payload['url'])->toBe(DeskAbsenceResource::getUrl('index'));
    }
});

/**
 * Les liens des emails partaient sur `config('app.url')`, qui pointe le domaine
 * ADMIN (`APP_URL=https://admin.ecoworking.fr`) : « Voir mes documents » et
 * « Voir mes factures » envoyaient les membres sur un back-office qui leur est
 * interdit. Le domaine portail (ADR-0004) est la seule source de vérité.
 */
it('pointe les liens des emails vers le domaine portail, jamais le domaine admin', function () {
    config([
        'domains.portal' => 'portail.ecoworking.test',
        'domains.admin' => 'admin.ecoworking.test',
        'app.url' => 'https://admin.ecoworking.test',
    ]);

    $recipient = User::factory()->create();
    $document = InternalDocument::factory()->create();
    $invoice = Invoice::factory()->issued()->create();

    $urls = [
        (new InternalDocumentPublishedNotification($document))->toMail($recipient),
        (new InvoiceIssuedNotification($invoice))->toMail($recipient),
        (new InvoiceOverdueNotification($invoice))->toMail($recipient),
    ];

    foreach ($urls as $mail) {
        expect($mail->actionUrl)->toStartWith('https://portail.ecoworking.test/')
            ->and($mail->actionUrl)->not->toContain('admin.ecoworking.test');
    }
});

it('envoie « Voir mes documents » sur la page documents du portail', function () {
    config(['domains.portal' => 'portail.ecoworking.test', 'app.url' => 'https://admin.ecoworking.test']);

    $mail = (new InternalDocumentPublishedNotification(InternalDocument::factory()->create()))
        ->toMail(User::factory()->create());

    expect($mail->actionUrl)->toBe('https://portail.ecoworking.test/documents');
});
