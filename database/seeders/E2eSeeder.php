<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Audience;
use App\Enums\ResourceAssignment;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Company;
use App\Models\Contact;
use App\Models\InternalDocument;
use App\Models\Invoice;
use App\Models\InvoiceCounter;
use App\Models\InvoiceLine;
use App\Models\MemberProfile;
use App\Models\Resource;
use App\Models\User;
use App\Services\IssueInvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Jeu de données DÉDIÉ à la suite e2e SPA (Playwright, C11.3 — cf.
 * docs/testing-e2e.md). Chargé uniquement sur la base `e2e` par
 * portal-spa/e2e/serve.sh (`migrate:fresh --seed --seeder=E2eSeeder`).
 *
 * Reproductible : toutes les données assertées par les specs Playwright sont
 * fixes (identifiants, titres, montants) — jamais de faker pour ce qui est
 * asserté. Les specs référencent les constantes ci-dessous via
 * portal-spa/e2e/support/seed.ts (à maintenir en miroir).
 */
final class E2eSeeder extends Seeder
{
    /** Membre résident principal (login UI, résa, factures, RSVP, validation). */
    public const string MEMBER_EMAIL = 'membre.e2e@ecoworking.test';

    /** Second résident : opt-out annuaire + créateur du conflit de résa. */
    public const string OTHER_EMAIL = 'autre.e2e@ecoworking.test';

    /** Mot de passe commun aux comptes e2e (base dédiée, jamais en prod). */
    public const string PASSWORD = 'e2e-password';

    /**
     * Bureau attitré du membre principal (`svg_desk_id` du ResourceSeeder).
     * Requis depuis le lot B : `/api/user` expose `has_desk` et le module
     * « Ma présence » n'est accessible qu'avec un bureau. `desk-1` reste libre —
     * directory.spec.ts l'utilise comme exemple de bureau non attribué.
     */
    public const string MEMBER_DESK_SVG_ID = 'desk-2';

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            OfferSeeder::class,
            ResourceSeeder::class, // 49 bureaux + 3 salles + salle événementielle
        ]);

        $company = Company::factory()->create([
            'legal_name' => 'Atelier Numérique E2E',
        ]);

        // --- Membres ------------------------------------------------------
        $member = User::factory()->resident()->create([
            'first_name' => 'Emma',
            'last_name' => 'Membre',
            'email' => self::MEMBER_EMAIL,
            'password' => Hash::make(self::PASSWORD),
        ]);
        // Rôle additionnel : contact facturation (PRD) — sans lui, un résident
        // ne voit AUCUNE facture, même à son nom (InvoiceController::scope…).
        $member->assignRole(Role::BillingContact->value);

        // Contact facturation explicite sur l'entité : c'est LUI qui ouvre les
        // coordonnées bancaires du bloc « Mon entreprise » (PRD §3.6.4,
        // CompanyPolicy::viewBillingDetails) — le rattachement de membre ne
        // suffit pas.
        Contact::factory()->billing()->primary()->create([
            'company_id' => $company->id,
            'user_id' => $member->id,
            'first_name' => 'Emma',
            'last_name' => 'Membre',
            'email' => self::MEMBER_EMAIL,
        ]);

        $memberDesk = Resource::query()->where('svg_desk_id', self::MEMBER_DESK_SVG_ID)->firstOrFail();
        $memberDesk->update(['assignment' => ResourceAssignment::AssignedResident->value]);

        MemberProfile::factory()->inDirectory()->create([
            'user_id' => $member->id,
            'company_id' => $company->id,
            'desk_id' => $memberDesk->id,
            'job_title' => 'Designer produit',
            'bio' => 'Profil e2e visible dans l’annuaire.',
        ]);

        $other = User::factory()->resident()->create([
            'first_name' => 'Hugo',
            'last_name' => 'Discret',
            'email' => self::OTHER_EMAIL,
            'password' => Hash::make(self::PASSWORD),
        ]);
        MemberProfile::factory()->create([
            'user_id' => $other->id,
            'company_id' => $company->id,
            'job_title' => 'Développeur',
            'show_in_directory' => false, // opt-out : ne doit PAS apparaître
        ]);

        // --- Facture émise au nom du membre --------------------------------
        // Via le VRAI pipeline d'émission (numéro chronologique + PDF généré,
        // queue sync dans l'env e2e) : le téléchargement PDF est testé de bout
        // en bout. Compteur pré-positionné à 90000 : le PDF est écrit dans le
        // storage/ partagé avec le dev — un numéro de la plage courante
        // (EW-…-00001) pourrait écraser le PDF d'une vraie facture de dev.
        // Numéro attendu : EW-{année}-90001 (miroir e2e/support/seed.ts).
        InvoiceCounter::query()->create(['year' => now()->year, 'value' => 90000]);
        $invoice = Invoice::factory()->create([
            'billable_type' => 'user',
            'billable_id' => $member->id,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Abonnement bureau résident',
            'quantity' => 1,
            'unit_price_ht' => 328.50,
            'vat_rate' => 20.00,
            'line_total_ht' => 328.50,
            'line_vat' => 65.70,
            'line_total_ttc' => 394.20,
        ]);
        app(IssueInvoiceService::class)->issue($invoice);

        // --- Annonces (C12.3) ----------------------------------------------
        // published_at décalé d'un jour : évite le piège timezone (+2 h) sur
        // les comparaisons de fraîcheur.
        Announcement::factory()->published()->create([
            'title' => 'Nouvelle machine à café au rez-de-chaussée',
            'body' => 'La machine à café du rez-de-chaussée a été remplacée. Les grains sont torréfiés à Lyon.',
            'visibility' => Audience::All->value,
            'published_at' => now()->subDay(),
        ]);
        Announcement::factory()->published()->event()->create([
            'title' => 'Apéro coworking du mois',
            'body' => 'Rendez-vous pour l’apéro mensuel, boissons offertes.',
            'visibility' => Audience::All->value,
            'published_at' => now()->subDay(),
            'event_starts_at' => now()->addDays(10)->setTime(18, 30),
            'event_ends_at' => now()->addDays(10)->setTime(20, 30),
            'location' => 'Cuisine du 1er étage',
            'requires_registration' => true,
            'max_participants' => 12,
        ]);

        // --- Document interne à valider (C12.4) -----------------------------
        Storage::put('internal-documents/e2e-charte.pdf', self::MINIMAL_PDF);
        InternalDocument::factory()->create([
            'title' => 'Charte du coworking',
            'version' => '2.0',
            'audience' => Audience::All->value,
            'published_at' => now()->subDay(),
            'pdf_path' => 'internal-documents/e2e-charte.pdf',
        ]);
    }

    /** PDF minimal valide (1 page vide) pour le téléchargement de documents. */
    private const string MINIMAL_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n164\n%%EOF";
}
