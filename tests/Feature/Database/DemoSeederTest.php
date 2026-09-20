<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\ResourceAssignment;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Announcement;
use App\Models\DeskAbsence;
use App\Models\DeskOccupation;
use App\Models\Invoice;
use App\Models\Resource;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Le jeu de démo (docs/recette.md) passe par les vrais services métier : ces
 * tests garantissent qu'il reste chargeable et couvre bien tous les états
 * attendus par la recette (sinon la recette teste dans le vide).
 */
beforeEach(function (): void {
    Storage::fake();
});

it('refuse de tourner en production', function (): void {
    $env = $this->app['env'];
    $this->app['env'] = 'production';

    try {
        // Appel direct (pas via `db:seed`, qui demanderait une confirmation en prod).
        expect(fn () => (new DemoSeeder)->run())
            ->toThrow(RuntimeException::class, 'interdit en production');
    } finally {
        $this->app['env'] = $env;
    }
});

it('charge le jeu de démo complet avec tous les états attendus par la recette', function (): void {
    $this->seed(DemoSeeder::class);

    // --- Comptes (mot de passe commun) -----------------------------------------
    foreach ([
        DemoSeeder::CLAIRE_EMAIL, DemoSeeder::MARC_EMAIL, DemoSeeder::INES_EMAIL, DemoSeeder::JULIEN_EMAIL,
        DemoSeeder::SOPHIE_EMAIL, DemoSeeder::KARIM_EMAIL, DemoSeeder::THOMAS_EMAIL, DemoSeeder::LEA_EMAIL,
        DemoSeeder::CAMILLE_EMAIL,
    ] as $email) {
        expect(User::query()->where('email', $email)->exists())->toBeTrue("compte manquant : {$email}");
    }

    $claire = User::query()->where('email', DemoSeeder::CLAIRE_EMAIL)->firstOrFail();
    expect($claire->hasRole('resident'))->toBeTrue()
        ->and($claire->hasRole('billing_contact'))->toBeTrue()
        ->and($claire->memberProfile->desk_id)->not->toBeNull();

    // --- Paul : anonymisé (soft delete, PII effacée, factures intactes) ------------
    expect(User::query()->where('email', DemoSeeder::PAUL_EMAIL)->exists())->toBeFalse()
        ->and(User::onlyTrashed()->whereNotNull('anonymized_at')->count())->toBe(1);

    // --- Bureaux : attribution alignée sur les profils -----------------------------
    $assignment = fn (int $n): ?ResourceAssignment => Resource::query()->where('svg_desk_id', "desk-{$n}")->value('assignment');
    expect($assignment(1))->toBe(ResourceAssignment::AssignedResident)
        ->and($assignment(30))->toBe(ResourceAssignment::AssignedResident)
        ->and($assignment(29))->toBe(ResourceAssignment::AssignedStaff)
        ->and($assignment(10))->toBe(ResourceAssignment::Unassigned);

    // --- Facturation : tous les statuts + numérotation sans trou --------------------
    $statuses = Invoice::query()->pluck('status')->map(fn ($s) => $s instanceof InvoiceStatus ? $s->value : $s)->unique();
    expect($statuses)->toContain(
        InvoiceStatus::Draft->value,
        InvoiceStatus::Paid->value,
        InvoiceStatus::PartiallyPaid->value,
        InvoiceStatus::Overdue->value,
        InvoiceStatus::Cancelled->value,
    );
    expect(Invoice::query()->where('is_credit_note', true)->count())->toBe(1)
        ->and(Invoice::query()->where('status', InvoiceStatus::Draft->value)->whereNotNull('number')->count())->toBe(0);

    $numbers = Invoice::query()->whereNotNull('number')->orderBy('number')->pluck('number');
    $sequence = $numbers->map(fn (string $n): int => (int) substr($n, -5))->values();
    expect($sequence->all())->toBe(range(1, $numbers->count()), 'numérotation avec trou ou doublon');

    // PDF générés (queue sync) pour toutes les factures émises.
    Invoice::query()->whereNotNull('number')->each(function (Invoice $invoice): void {
        expect($invoice->pdf_path)->not->toBeNull()
            ->and(Storage::exists($invoice->pdf_path))->toBeTrue("PDF absent : {$invoice->number}");
    });

    // --- Tickets de Thomas : 10 bureau (4 utilisés) + 2 salle (1 utilisé) -----------
    $thomas = User::query()->where('email', DemoSeeder::THOMAS_EMAIL)->firstOrFail();
    $count = fn (TicketType $type, TicketStatus $status): int => Ticket::query()
        ->where('user_id', $thomas->id)->where('type', $type->value)->where('status', $status->value)->count();
    expect($count(TicketType::DeskHalfDay, TicketStatus::Available))->toBe(6)
        ->and($count(TicketType::DeskHalfDay, TicketStatus::Used))->toBe(4)
        ->and($count(TicketType::MeetingRoomHalfDay, TicketStatus::Available))->toBe(1)
        ->and($count(TicketType::MeetingRoomHalfDay, TicketStatus::Used))->toBe(1)
        ->and(DeskOccupation::query()->where('user_id', $thomas->id)->count())->toBe(4);

    // Léa : aucun ticket (états vides).
    $lea = User::query()->where('email', DemoSeeder::LEA_EMAIL)->firstOrFail();
    expect(Ticket::query()->where('user_id', $lea->id)->count())->toBe(0);

    // --- Absences (récurrente + plage + demi-journée admin) ---------------------------
    expect(DeskAbsence::query()->count())->toBe(3)
        ->and(DeskAbsence::query()->where('recurrence_type', 'weekly')->count())->toBe(1)
        // Toutes tracent leur auteur comme les vrais chemins de création
        // (portail = le membre, back-office = l'admin) — recette 2026-09-20.
        ->and(DeskAbsence::query()->whereNotNull('created_by')->count())->toBe(3)
        ->and(DeskAbsence::query()->whereHas('createdBy', fn ($query) => $query->where('email', DemoSeeder::ADMIN_EMAIL))->count())->toBe(1);

    // --- Annonces : publiées (info/alerte/événement) + 1 brouillon ---------------------
    expect(Announcement::query()->where('status', 'published')->count())->toBe(5)
        ->and(Announcement::query()->where('status', 'draft')->count())->toBe(1)
        ->and(Announcement::query()->where('type', 'alert')->count())->toBe(1);

    // Publication → notifications in-app pour l'audience (observer, queue sync).
    expect($claire->notifications()->count())->toBeGreaterThan(0);
});
