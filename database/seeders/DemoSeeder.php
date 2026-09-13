<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdministrativeDocumentType;
use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\Audience;
use App\Enums\BookingStatus;
use App\Enums\ContactRole;
use App\Enums\DeskAbsenceRecurrence;
use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\InternalDocumentType;
use App\Enums\MemberProfileStatus;
use App\Enums\PaymentMethod;
use App\Enums\Period;
use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\TicketType;
use App\Models\AdministrativeDocument;
use App\Models\Announcement;
use App\Models\AnnouncementRegistration;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Contact;
use App\Models\DeskOccupation;
use App\Models\InternalDocument;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\MemberDocumentValidation;
use App\Models\MemberProfile;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AnonymizeUserService;
use App\Services\BookingService;
use App\Services\CancelInvoiceService;
use App\Services\DeskAvailabilityService;
use App\Services\IssueInvoiceService;
use App\Services\MonthlyBillingService;
use App\Services\PresenceService;
use App\Services\PurchaseService;
use App\Services\TicketService;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Jeu de données de DÉMO pour la recette manuelle (docs/recette.md).
 *
 * Chargé sur la base de DEV uniquement :
 *   sail artisan migrate:fresh --seed --seeder=DemoSeeder
 *
 * Objectif : couvrir tous les états visibles dans le portail et le back-office
 * (rôles cumulés, factures payée/partielle/en retard/annulée+avoir/brouillon,
 * tickets disponibles/utilisés, résas passées/futures/annulées, absences
 * ponctuelles/récurrentes, annonces info/événement/brouillon, documents à
 * valider, membre anonymisé…). Passe par les VRAIS services métier (émission,
 * numérotation, anti-double-booking, consommation de tickets) — jamais par des
 * écritures brutes contournant les règles §3.6.
 *
 * Identifiants fixes (comptes listés en fin de run) ; mot de passe commun
 * `demo-password`. Refuse de tourner en production.
 */
final class DemoSeeder extends Seeder
{
    /** Mot de passe commun à tous les comptes de démo (dev only). */
    public const string PASSWORD = 'demo-password';

    public const string ADMIN_EMAIL = 'admin@ecoworking.fr';

    public const string CLAIRE_EMAIL = 'claire.fontaine@atelier-lumiere.demo';

    public const string MARC_EMAIL = 'marc.delorme@atelier-lumiere.demo';

    public const string INES_EMAIL = 'ines.rahmani@atelier-lumiere.demo';

    public const string JULIEN_EMAIL = 'julien.petit@atelier-lumiere.demo';

    public const string SOPHIE_EMAIL = 'sophie.verger@studio-verger.demo';

    public const string KARIM_EMAIL = 'karim.haddad@studio-verger.demo';

    public const string THOMAS_EMAIL = 'thomas.bernard@demo.fr';

    public const string LEA_EMAIL = 'lea.moreau@nova-conseil.demo';

    public const string CAMILLE_EMAIL = 'camille.roux@ecoworking.fr';

    public const string PAUL_EMAIL = 'paul.ancien@studio-verger.demo';

    /** PDF minimal valide (1 page vide) pour les téléchargements de documents. */
    private const string MINIMAL_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n164\n%%EOF";

    private User $admin;

    private CarbonImmutable $today;

    /** @var array<string, Offer> */
    private array $offers = [];

    /** @var array<string, resource> */
    private array $rooms = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder : interdit en production (données fictives + mot de passe commun).');
        }

        // Jobs (PDF facture) et notifications exécutés immédiatement : la base
        // de démo est complète sans lancer de worker de queue.
        config(['queue.default' => 'sync']);

        $this->today = CarbonImmutable::today();

        $this->call(DatabaseSeeder::class);
        $this->admin = User::query()->where('email', self::ADMIN_EMAIL)->firstOrFail();

        $this->offers = Offer::query()->get()->keyBy('code')->all();
        $this->rooms = Resource::query()->ofType(ResourceType::MeetingRoom)->orderBy('display_order')->get()
            ->keyBy(fn (Resource $room): string => 'room'.substr($room->name, -1))->all();

        $atelier = $this->seedAtelierLumiere();
        $studio = $this->seedStudioVerger();
        $thomas = $this->seedThomasExternal();
        $this->seedLeaExternalSansTicket();
        $this->seedStaff();

        $this->seedBilling($atelier['company'], $studio['company'], $thomas['user'], $thomas['company']);
        $this->seedBookings($atelier, $studio, $thomas['user'], $thomas['company']);
        $this->seedDeskOccupations($thomas['user']);
        $this->seedAbsences($atelier['ines'], $studio['sophie']);
        $this->seedAnnouncements([$atelier['claire'], $atelier['marc'], $studio['sophie']]);
        $this->seedInternalDocuments($atelier['claire'], $atelier['marc']);
        $this->seedAdministrativeDocuments($atelier['company'], $studio['company']);
        $this->seedAnonymizedMember($studio['paul']);

        // Bascule des factures dont l'échéance est dépassée (+ notif retard).
        Artisan::call('invoices:update-overdue');

        $this->printAccounts();
    }

    // -------------------------------------------------------------------------
    // Entités & membres
    // -------------------------------------------------------------------------

    /**
     * Atelier Lumière (SAS) : 3 résidents + 1 additional, domiciliation,
     * remise négociée 10 %, mandat SEPA. Claire = contact facturation.
     *
     * @return array{company: Company, claire: User, marc: User, ines: User, julien: User}
     */
    private function seedAtelierLumiere(): array
    {
        $company = Company::factory()->withDiscount(10.0)->create([
            'legal_name' => 'Atelier Lumière',
            'legal_form' => 'SAS',
            'siret' => '84512345600017',
            'vat_number' => 'FR12845123456',
            'billing_email' => 'compta@atelier-lumiere.demo',
            'address_line1' => '12 rue de la République',
            'postal_code' => '69002',
            'city' => 'Lyon',
            'preferred_payment_method' => PaymentMethod::Sepa->value,
            'sepa_iban_last4' => '4821',
            'sepa_mandate_reference' => 'ECW-2024-0007',
            'sepa_mandate_signed_at' => $this->today->subMonths(8)->toDateString(),
            'discount_note' => 'Remise 10 % négociée (3 bureaux)',
            'admin_notes' => 'Client historique, très autonome.',
        ]);

        $claire = $this->member(self::CLAIRE_EMAIL, 'Claire', 'Fontaine', Role::Resident, $company, desk: 1, profile: [
            'job_title' => 'Directrice artistique',
            'bio' => 'Fondatrice de l’Atelier Lumière, studio de design graphique et motion. J’aime les typographies bien faites et le café bien serré.',
            'interests' => 'Typographie, photographie argentique, escalade',
            'linkedin_url' => 'https://www.linkedin.com/in/claire-fontaine-demo',
            'website_url' => 'https://atelier-lumiere.demo',
            'show_in_directory' => true,
            'newsletter_opt_in' => true,
            'arrival_date' => $this->today->subMonths(8)->toDateString(),
        ]);
        $claire->assignRole(Role::BillingContact->value);
        Contact::factory()->billing()->primary()->create([
            'company_id' => $company->id,
            'user_id' => $claire->id,
            'first_name' => 'Claire',
            'last_name' => 'Fontaine',
            'email' => self::CLAIRE_EMAIL,
            'phone' => '06 12 34 56 78',
        ]);
        Contact::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Bernard',
            'last_name' => 'Comptable',
            'email' => 'cabinet@expert-compta.demo',
            'phone' => '04 78 00 00 01',
            'role' => ContactRole::Management->value,
            'notes' => 'Cabinet comptable externe — copie des factures sur demande.',
        ]);

        $marc = $this->member(self::MARC_EMAIL, 'Marc', 'Delorme', Role::Resident, $company, desk: 2, profile: [
            'job_title' => 'Motion designer',
            'show_in_directory' => false, // opt-out : « Coworker (souhaite rester discret) »
            'arrival_date' => $this->today->subMonths(6)->toDateString(),
        ]);

        $ines = $this->member(self::INES_EMAIL, 'Inès', 'Rahmani', Role::Resident, $company, desk: 3, profile: [
            'job_title' => 'Développeuse front',
            'bio' => 'React, accessibilité et design systems. Télétravail le vendredi.',
            'interests' => 'A11y, vélo, cuisine libanaise',
            'show_in_directory' => true,
            'arrival_date' => $this->today->subMonths(4)->toDateString(),
        ]);

        $julien = $this->member(self::JULIEN_EMAIL, 'Julien', 'Petit', Role::Additional, $company, desk: null, profile: [
            'job_title' => 'Stagiaire design',
            'show_in_directory' => true,
            'arrival_date' => $this->today->subMonths(2)->toDateString(),
        ]);

        $start = $this->today->subMonths(4)->startOfMonth()->toDateString();
        foreach ([$claire, $marc, $ines] as $resident) {
            $this->subscription('resident_desk', $resident, $company, $start);
        }
        $this->subscription('additional_person', $julien, $company, $this->today->subMonths(2)->startOfMonth()->toDateString());
        Subscription::factory()->domiciliation($company)->create([
            'offer_id' => $this->offers['domiciliation']->id,
            'starts_at' => $start,
            'billing_day' => 1,
        ]);

        return compact('company', 'claire', 'marc', 'ines', 'julien');
    }

    /**
     * Studio Verger (SARL) : 1 résidente contact facturation (factures en
     * retard / partielle) + 1 résident en pause.
     *
     * Paul (parti, abonnement terminé) est créé ICI pour apparaître sur les
     * factures passées ; son anonymisation intervient en fin de run.
     *
     * @return array{company: Company, sophie: User, karim: User, paul: User}
     */
    private function seedStudioVerger(): array
    {
        $company = Company::factory()->create([
            'legal_name' => 'Studio Verger',
            'legal_form' => 'SARL',
            'siret' => '90187654300021',
            'vat_number' => 'FR45901876543',
            'billing_email' => 'sophie@studio-verger.demo',
            'address_line1' => '8 quai Saint-Antoine',
            'postal_code' => '69002',
            'city' => 'Lyon',
            'preferred_payment_method' => PaymentMethod::Transfer->value,
            'admin_notes' => 'Paie souvent en retard — relancer à J+7.',
        ]);

        $sophie = $this->member(self::SOPHIE_EMAIL, 'Sophie', 'Verger', Role::Resident, $company, desk: 30, profile: [
            'job_title' => 'Architecte d’intérieur',
            'bio' => 'Rénovation d’appartements lyonnais et de petits commerces.',
            'interests' => 'Brocante, céramique',
            'show_in_directory' => true,
            'arrival_date' => $this->today->subMonths(5)->toDateString(),
        ]);
        $sophie->assignRole(Role::BillingContact->value);
        Contact::factory()->billing()->primary()->create([
            'company_id' => $company->id,
            'user_id' => $sophie->id,
            'first_name' => 'Sophie',
            'last_name' => 'Verger',
            'email' => self::SOPHIE_EMAIL,
        ]);

        $karim = $this->member(self::KARIM_EMAIL, 'Karim', 'Haddad', Role::Resident, $company, desk: 31, profile: [
            'job_title' => 'Économiste de la construction',
            'status' => MemberProfileStatus::Paused->value,
            'show_in_directory' => true,
            'arrival_date' => $this->today->subMonths(5)->toDateString(),
        ]);

        $start = $this->today->subMonths(4)->startOfMonth()->toDateString();
        $this->subscription('resident_desk', $sophie, $company, $start);
        $this->subscription('resident_desk', $karim, $company, $start, [
            'status' => SubscriptionStatus::Paused->value,
            'paused_at' => $this->today->subDays(10),
            'notes' => 'Pause 2 mois (mission à Paris).',
        ]);

        $paul = $this->member(self::PAUL_EMAIL, 'Paul', 'Ancien', Role::Resident, $company, desk: null, profile: [
            'job_title' => 'Géomètre',
            'status' => MemberProfileStatus::Left->value,
            'arrival_date' => $this->today->subYear()->toDateString(),
            'departure_date' => $this->today->subMonths(2)->toDateString(),
        ]);
        $this->subscription('resident_desk', $paul, $company, $this->today->subYear()->startOfMonth()->toDateString(), [
            'status' => SubscriptionStatus::Ended->value,
            'ends_at' => $this->today->subMonths(2)->endOfMonth()->toDateString(),
        ]);

        return compact('company', 'sophie', 'karim', 'paul');
    }

    /**
     * Thomas Bernard : external en nom propre (entité `individual`), contact
     * facturation de lui-même, tickets bureau + salle crédités par facture.
     *
     * @return array{company: Company, user: User}
     */
    private function seedThomasExternal(): array
    {
        $company = Company::factory()->individual()->create([
            'first_name' => 'Thomas',
            'last_name' => 'Bernard',
            'billing_email' => self::THOMAS_EMAIL,
            'address_line1' => '3 rue des Capucins',
            'postal_code' => '69001',
            'city' => 'Lyon',
            'preferred_payment_method' => PaymentMethod::Card->value,
        ]);

        $user = $this->member(self::THOMAS_EMAIL, 'Thomas', 'Bernard', Role::External, $company, desk: null, profile: [
            'job_title' => 'Consultant indépendant',
            'show_in_directory' => false,
            'arrival_date' => $this->today->subMonths(3)->toDateString(),
        ]);
        $user->assignRole(Role::BillingContact->value);
        Contact::factory()->billing()->primary()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'first_name' => 'Thomas',
            'last_name' => 'Bernard',
            'email' => self::THOMAS_EMAIL,
        ]);

        return compact('company', 'user');
    }

    /** Léa Moreau : external rattachée à une entreprise, AUCUN ticket (états vides). */
    private function seedLeaExternalSansTicket(): User
    {
        $company = Company::factory()->create([
            'legal_name' => 'Nova Conseil',
            'legal_form' => 'SASU',
            'siret' => '91234567800013',
            'billing_email' => 'facturation@nova-conseil.demo',
            'address_line1' => '25 avenue Jean Jaurès',
            'postal_code' => '69007',
            'city' => 'Lyon',
        ]);

        return $this->member(self::LEA_EMAIL, 'Léa', 'Moreau', Role::External, $company, desk: null, profile: [
            'job_title' => 'Consultante RH',
            'show_in_directory' => false,
            'arrival_date' => $this->today->subDays(3)->toDateString(),
        ]);
    }

    /** Camille Roux : staff Ecoworking, bureau attitré staff (bureau 29). */
    private function seedStaff(): User
    {
        $ecoworking = Company::factory()->create([
            'legal_name' => 'Ecoworking',
            'legal_form' => 'SARL',
            'siret' => '75012345600019',
            'billing_email' => 'contact@ecoworking.fr',
            'address_line1' => '10 rue de l’Exemple',
            'postal_code' => '69001',
            'city' => 'Lyon',
        ]);

        return $this->member(self::CAMILLE_EMAIL, 'Camille', 'Roux', Role::Staff, $ecoworking, desk: 29, profile: [
            'job_title' => 'Office manager',
            'bio' => 'Votre interlocutrice au quotidien : badges, salles, café.',
            'show_in_directory' => true,
            'arrival_date' => $this->today->subYears(2)->toDateString(),
        ], assignment: ResourceAssignment::AssignedStaff);
    }

    /**
     * Crée un user + profil membre, attribue le bureau `desk` (n° du plan) si fourni.
     *
     * @param  array<string, mixed>  $profile
     */
    private function member(
        string $email,
        string $firstName,
        string $lastName,
        Role $role,
        Company $company,
        ?int $desk,
        array $profile,
        ResourceAssignment $assignment = ResourceAssignment::AssignedResident,
    ): User {
        $user = User::factory()->withRole($role)->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
        ]);

        $deskId = null;
        if ($desk !== null) {
            $resource = Resource::query()->where('svg_desk_id', "desk-{$desk}")->firstOrFail();
            $resource->update(['assignment' => $assignment->value]);
            $deskId = $resource->id;
        }

        MemberProfile::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'desk_id' => $deskId,
            'bio' => null,
            'interests' => null,
            'linkedin_url' => null,
            'website_url' => null,
            'newsletter_opt_in' => false,
            ...$profile,
        ]);

        return $user->fresh(['memberProfile']);
    }

    /** @param  array<string, mixed>  $extra */
    private function subscription(string $offerCode, User $subscriber, Company $billable, string $startsAt, array $extra = []): Subscription
    {
        return Subscription::factory()->create([
            'offer_id' => $this->offers[$offerCode]->id,
            'subscriber_type' => 'user',
            'subscriber_id' => $subscriber->id,
            'billable_type' => 'company',
            'billable_id' => $billable->id,
            'starts_at' => $startsAt,
            'billing_day' => 1,
            ...$extra,
        ]);
    }

    // -------------------------------------------------------------------------
    // Facturation
    // -------------------------------------------------------------------------

    /**
     * 3 mois de facturation récurrente émis via le vrai pipeline (numérotation
     * chronologique, PDF, notif), puis paiements gradués :
     *   M-3 : tout payé · M-2 : Atelier payé, Studio acompte 50 % (→ en retard,
     *   partiellement payée) · M-1 : Atelier payé, Studio impayé (→ en retard) ·
     *   mois courant : brouillons non émis.
     * + facture tickets de Thomas (échéance future, acompte CB → « partielle »)
     * + une facture annulée avec avoir.
     */
    private function seedBilling(Company $atelier, Company $studio, User $thomas, Company $thomasEntity): void
    {
        $billing = app(MonthlyBillingService::class);
        $issue = app(IssueInvoiceService::class);

        foreach ([3, 2, 1] as $monthsAgo) {
            $month = $this->today->subMonths($monthsAgo)->startOfMonth();
            $issuedAt = $month->addDay(); // émission le 2 du mois (cron du 1er à 6 h UTC)

            foreach ($billing->generateMonth($month) as $draft) {
                $invoice = $this->issueBackdated($issue, $draft, $issuedAt);

                $isAtelier = $invoice->billable_type === 'company' && (int) $invoice->billable_id === $atelier->id;
                $isStudio = $invoice->billable_type === 'company' && (int) $invoice->billable_id === $studio->id;

                if ($monthsAgo === 3 || $isAtelier) {
                    $this->pay($invoice, (float) $invoice->total_ttc, $issuedAt->addDays(5), $isAtelier ? PaymentMethod::Sepa : PaymentMethod::Transfer);
                } elseif ($monthsAgo === 2 && $isStudio) {
                    $this->pay($invoice, round((float) $invoice->total_ttc / 2, 2), $issuedAt->addDays(20), PaymentMethod::Transfer, 'VIR 2024-ACOMPTE');
                }
                // M-1 Studio : rien → passera `overdue` via invoices:update-overdue.
            }
        }

        // Brouillons du mois courant : visibles côté admin, invisibles côté portail.
        $billing->generateMonth($this->today->startOfMonth());

        // --- Tickets de Thomas : facture « pack 10 bureau » + 2 tickets salle ---
        $purchases = app(PurchaseService::class);
        $pack = $purchases->createFromOffer($this->offers['desk_half_day_pack_10'], $thomas, $thomasEntity, $this->admin->id)['purchase'];
        $roomTicketA = $purchases->createFromOffer($this->offers['meeting_room_half_day'], $thomas, $thomasEntity, $this->admin->id)['purchase'];
        $roomTicketB = $purchases->createFromOffer($this->offers['meeting_room_half_day'], $thomas, $thomasEntity, $this->admin->id)['purchase'];

        $ticketsInvoice = Invoice::factory()->create([
            'billable_type' => 'company',
            'billable_id' => $thomasEntity->id,
        ]);
        foreach ([[$pack, 1], [$roomTicketA, 2]] as [$purchase, $quantity]) {
            $lineHt = round((float) $purchase->unit_price_ht * $quantity, 2);
            $lineVat = round($lineHt * (float) $purchase->vat_rate / 100, 2);
            InvoiceLine::factory()->create([
                'invoice_id' => $ticketsInvoice->id,
                'description' => $purchase->label,
                'quantity' => $quantity,
                'unit_price_ht' => $purchase->unit_price_ht,
                'vat_rate' => $purchase->vat_rate,
                'line_total_ht' => $lineHt,
                'line_vat' => $lineVat,
                'line_total_ttc' => round($lineHt + $lineVat, 2),
            ]);
        }
        // Émise il y a 5 jours (échéance dans 9 jours) : l'acompte la laisse en
        // « partiellement payée » sans que le cron overdue la bascule.
        $ticketsInvoice = $this->issueBackdated($issue, $ticketsInvoice, $this->today->subDays(5));
        foreach ([$pack, $roomTicketA, $roomTicketB] as $purchase) {
            $purchase->forceFill(['invoice_id' => $ticketsInvoice->id])->saveQuietly();
        }
        $this->pay($ticketsInvoice, 100.00, $this->today->subDays(5), PaymentMethod::Card, 'TPE 4521 — acompte');

        // --- Facture émise par erreur → annulée + avoir (seule voie légale §3.6) ---
        $duplicate = Invoice::factory()->create([
            'billable_type' => 'company',
            'billable_id' => $atelier->id,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $duplicate->id,
            'description' => 'Location salle événementielle — soirée du '.$this->today->subDays(40)->format('d/m/Y'),
            'quantity' => 1,
            'unit_price_ht' => 250.00,
            'vat_rate' => 20.00,
            'line_total_ht' => 250.00,
            'line_vat' => 50.00,
            'line_total_ttc' => 300.00,
        ]);
        $duplicate = $this->issueBackdated($issue, $duplicate, $this->today->subDays(35));
        app(CancelInvoiceService::class)->cancel($duplicate, 'Doublon : prestation déjà facturée sur la facture mensuelle.', $this->admin->id);
    }

    /** Émet via le service (numéro + PDF + notif) puis rétro-date l'émission pour la démo. */
    private function issueBackdated(IssueInvoiceService $issue, Invoice $draft, CarbonImmutable $issuedAt): Invoice
    {
        $invoice = $issue->issue($draft, $this->admin->id);

        $invoice->forceFill([
            'issued_at' => $issuedAt->toDateString(),
            'due_at' => $issuedAt->addDays(14)->toDateString(),
            'created_at' => $issuedAt,
        ])->saveQuietly();

        return $invoice->fresh();
    }

    private function pay(Invoice $invoice, float $amount, CarbonImmutable $paidAt, PaymentMethod $method, ?string $reference = null): void
    {
        // PaymentObserver recalcule amount_paid + statut (payée / partielle).
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => number_format($amount, 2, '.', ''),
            'paid_at' => $paidAt->toDateString(),
            'method' => $method->value,
            'reference' => $reference,
            'created_by' => $this->admin->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Réservations, occupations, absences
    // -------------------------------------------------------------------------

    /**
     * @param  array{claire: User, marc: User, ines: User}  $atelier
     * @param  array{sophie: User}  $studio
     */
    private function seedBookings(array $atelier, array $studio, User $thomas, Company $thomasEntity): void
    {
        $bookings = app(BookingService::class);
        $d = fn (int $n): CarbonImmutable => $this->workingDay($n);

        // Résas futures via le service (verrou + GiST) — comme depuis le portail.
        $bookings->create([
            'resource' => $this->rooms['room2'],
            'user' => $atelier['claire'],
            'starts_at' => $d(1)->setTime(10, 0),
            'ends_at' => $d(1)->setTime(12, 0),
            'title' => 'Point client Mairie du 7e',
        ]);
        $bookings->create([
            'resource' => $this->rooms['room1'],
            'user' => $atelier['marc'],
            'starts_at' => $d(3)->setTime(14, 0),
            'ends_at' => $d(3)->setTime(16, 0),
            'title' => 'Relecture storyboard',
        ]);
        $bookings->create([
            'resource' => $this->rooms['room3'],
            'user' => $studio['sophie'],
            'starts_at' => $d(2)->setTime(9, 0),
            'ends_at' => $d(2)->setTime(18, 0),
            'title' => 'Atelier plans — journée complète',
        ]);
        $bookings->create([
            'resource' => $this->rooms['room2'],
            'user' => $atelier['ines'],
            'starts_at' => $d(7)->setTime(11, 0),
            'ends_at' => $d(7)->setTime(12, 30),
        ]);

        // Salle événementielle : réservation interne admin (invisible en résa portail).
        $eventRoom = Resource::query()->ofType(ResourceType::EventRoom)->firstOrFail();
        $bookings->create([
            'resource' => $eventRoom,
            'user' => null,
            'starts_at' => $d(4)->setTime(18, 30),
            'ends_at' => $d(4)->setTime(22, 0),
            'title' => 'Afterwork Ecoworking',
            'is_internal' => true,
            'created_by' => $this->admin->id,
        ]);

        // External : demi-journée avec ticket salle consommé (traçabilité « Mes tickets »).
        $roomTicket = app(TicketService::class)->lockFirstAvailable($thomas, TicketType::MeetingRoomHalfDay);
        $bookings->create([
            'resource' => $this->rooms['room1'],
            'user' => $thomas,
            'starts_at' => $d(2)->setTime(9, 0),
            'ends_at' => $d(2)->setTime(13, 0),
            'title' => 'Entretiens de recrutement',
            'billable' => $thomasEntity,
            'ticket' => $roomTicket,
        ]);

        // Historique : résas passées + une annulée (factory, hors service car passées).
        Booking::factory()->create([
            'resource_id' => $this->rooms['room2']->id,
            'user_id' => $atelier['claire']->id,
            'starts_at' => $this->workingDay(-5)->setTime(14, 0),
            'ends_at' => $this->workingDay(-5)->setTime(15, 30),
            'title' => 'Kick-off refonte site',
            'status' => BookingStatus::Confirmed->value,
        ]);
        Booking::factory()->create([
            'resource_id' => $this->rooms['room3']->id,
            'user_id' => $studio['sophie']->id,
            'starts_at' => $this->workingDay(-12)->setTime(9, 0),
            'ends_at' => $this->workingDay(-12)->setTime(13, 0),
            'status' => BookingStatus::Confirmed->value,
        ]);
        Booking::factory()->cancelled()->create([
            'resource_id' => $this->rooms['room1']->id,
            'user_id' => $atelier['claire']->id,
            'starts_at' => $d(5)->setTime(9, 0),
            'ends_at' => $d(5)->setTime(10, 0),
            'title' => 'Visio annulée',
            'cancel_reason' => 'Réunion déplacée en visio.',
        ]);
    }

    /** Thomas : 1 occupation bureau future via le service + 3 passées (tickets utilisés). */
    private function seedDeskOccupations(User $thomas): void
    {
        $desks = app(DeskAvailabilityService::class);
        $tickets = app(TicketService::class);

        $next = $this->workingDay(1);
        $freeDesk = $desks->availableDesks($next, Period::Morning)->first();
        $ticket = $tickets->lockFirstAvailable($thomas, TicketType::DeskHalfDay);
        $desks->bookForExternal($thomas, $freeDesk, $next, Period::Morning, $ticket, $this->admin->id);

        foreach ([-3, -8, -15] as $offset) {
            $date = $this->workingDay($offset);
            $desk = Resource::query()->ofType(ResourceType::Desk)
                ->where('assignment', ResourceAssignment::Unassigned->value)
                ->orderBy('display_order')->skip(abs($offset) % 5)->firstOrFail();
            $ticket = $tickets->lockFirstAvailable($thomas, TicketType::DeskHalfDay);

            $occupation = DeskOccupation::factory()->create([
                'desk_id' => $desk->id,
                'user_id' => $thomas->id,
                'date' => $date->toDateString(),
                'period' => $offset === -8 ? Period::Afternoon->value : Period::Morning->value,
                'source' => DeskOccupationSource::ExternalTicket->value,
                'status' => DeskOccupationStatus::Present->value,
                'ticket_id' => $ticket->id,
                'created_by' => $this->admin->id,
            ]);
            $tickets->consume($ticket, $occupation);
            $ticket->forceFill(['consumed_at' => $date->setTime(8, 45)])->saveQuietly();
        }
    }

    private function seedAbsences(User $ines, User $sophie): void
    {
        $presence = app(PresenceService::class);

        // Récurrence hebdo : télétravail le vendredi sur 3 mois (déjà commencée).
        $presence->declareAbsence([
            'user' => $ines,
            'date_start' => $this->today->subWeeks(2),
            'date_end' => $this->today->addMonths(3),
            'period' => Period::FullDay,
            'recurrence_type' => DeskAbsenceRecurrence::Weekly,
            'recurrence_day_of_week' => 5,
            'notes' => 'Télétravail le vendredi',
        ]);
        // Plage : congés la semaine prochaine.
        $presence->declareAbsence([
            'user' => $ines,
            'date_start' => $this->today->addWeek()->startOfWeek(),
            'date_end' => $this->today->addWeek()->endOfWeek(),
            'period' => Period::FullDay,
            'notes' => 'Congés',
        ]);
        // Jour unique demi-journée, saisie par l'admin (PRD §4.8.2).
        $presence->declareAbsence([
            'user' => $sophie,
            'date_start' => $this->workingDay(1),
            'period' => Period::Afternoon,
            'notes' => 'Rendez-vous chantier — saisi par l’accueil',
            'created_by' => $this->admin->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Communication & documents
    // -------------------------------------------------------------------------

    /** @param  list<User>  $registrants */
    private function seedAnnouncements(array $registrants): void
    {
        $base = ['created_by' => $this->admin->id];

        Announcement::factory()->published()->create($base + [
            'title' => 'Nouvelle machine à café au rez-de-chaussée',
            'body' => "La machine du rez-de-chaussée a été remplacée. Grains torréfiés à Lyon, déca disponible.\n\nMerci de signaler tout souci à l’accueil.",
            'published_at' => $this->today->subDays(3)->setTime(9, 0),
        ]);
        Announcement::factory()->published()->create($base + [
            'type' => AnnouncementType::Alert->value,
            'title' => 'Coupure d’eau jeudi matin',
            'body' => 'Intervention Eau du Grand Lyon jeudi de 8 h à 11 h : pas d’eau au 2e étage. Les sanitaires du 1er restent accessibles.',
            'published_at' => $this->today->subDay()->setTime(17, 30),
        ]);
        Announcement::factory()->published()->create($base + [
            'title' => 'Rappel : tri des déchets',
            'body' => 'Les bacs jaunes sont réservés aux emballages. Les gobelets compostables vont dans le bac marron de la cuisine.',
            'visibility' => Audience::Residents->value,
            'published_at' => $this->today->subDays(10)->setTime(12, 0),
        ]);

        $apero = Announcement::factory()->published()->event()->create($base + [
            'title' => 'Apéro coworking du mois',
            'body' => 'Rendez-vous pour l’apéro mensuel, boissons offertes. Amenez de quoi grignoter si le cœur vous en dit.',
            'published_at' => $this->today->subDays(5)->setTime(10, 0),
            'event_starts_at' => $this->workingDay(4)->setTime(18, 30),
            'event_ends_at' => $this->workingDay(4)->setTime(20, 30),
            'location' => 'Cuisine du 1er étage',
            'requires_registration' => true,
            'max_participants' => 12,
        ]);
        foreach ($registrants as $user) {
            AnnouncementRegistration::factory()->create([
                'announcement_id' => $apero->id,
                'user_id' => $user->id,
                'registered_at' => $this->today->subDays(4),
            ]);
        }

        $past = Announcement::factory()->published()->event()->create($base + [
            'title' => 'Atelier « Facturer sans stress »',
            'body' => 'Retour d’expérience sur la facturation électronique 2026-2027 avec un expert-comptable.',
            'published_at' => $this->today->subMonth()->setTime(10, 0),
            'event_starts_at' => $this->workingDay(-10)->setTime(12, 30),
            'event_ends_at' => $this->workingDay(-10)->setTime(14, 0),
            'location' => 'Salle de réunion 3',
            'requires_registration' => true,
            'max_participants' => 12,
        ]);
        AnnouncementRegistration::factory()->attended()->create([
            'announcement_id' => $past->id,
            'user_id' => $registrants[0]->id,
            'registered_at' => $this->today->subMonth(),
        ]);

        Announcement::factory()->create($base + [
            'type' => AnnouncementType::Event->value,
            'status' => AnnouncementStatus::Draft->value,
            'title' => 'Fête de fin d’année (brouillon)',
            'body' => 'À compléter : traiteur, date, jauge.',
            'event_starts_at' => $this->today->addMonths(3)->setTime(19, 0),
            'event_ends_at' => $this->today->addMonths(3)->setTime(23, 0),
            'location' => 'Salle événementielle',
            'requires_registration' => true,
            'max_participants' => 60,
        ]);
    }

    private function seedInternalDocuments(User $claire, User $marc): void
    {
        Storage::put('internal-documents/demo-charte-v2.pdf', self::MINIMAL_PDF);
        Storage::put('internal-documents/demo-cgu-v1.pdf', self::MINIMAL_PDF);
        Storage::put('internal-documents/demo-droit-image-v1.pdf', self::MINIMAL_PDF);

        $charte = InternalDocument::factory()->create([
            'type' => InternalDocumentType::Charter->value,
            'title' => 'Charte du coworking',
            'version' => '2.0',
            'audience' => Audience::All->value,
            'published_at' => $this->today->subDays(7),
            'pdf_path' => 'internal-documents/demo-charte-v2.pdf',
            'created_by' => $this->admin->id,
        ]);
        $cgu = InternalDocument::factory()->cgu()->create([
            'version' => '1.0',
            'audience' => Audience::All->value,
            'published_at' => $this->today->subMonths(6),
            'pdf_path' => 'internal-documents/demo-cgu-v1.pdf',
            'created_by' => $this->admin->id,
        ]);
        InternalDocument::factory()->create([
            'type' => InternalDocumentType::ImageRights->value,
            'title' => 'Autorisation de droit à l’image',
            'version' => '1.0',
            'audience' => Audience::Residents->value,
            'published_at' => $this->today->subMonths(6),
            'pdf_path' => 'internal-documents/demo-droit-image-v1.pdf',
            'created_by' => $this->admin->id,
        ]);

        // Claire a validé la charte v1.0 → la v2.0 redevient « à valider » (historique conservé).
        MemberDocumentValidation::factory()->create([
            'internal_document_id' => $charte->id,
            'user_id' => $claire->id,
            'version' => '1.0',
            'validated_at' => $this->today->subMonths(5),
        ]);
        MemberDocumentValidation::factory()->create([
            'internal_document_id' => $cgu->id,
            'user_id' => $claire->id,
            'version' => '1.0',
            'validated_at' => $this->today->subMonths(5),
        ]);
        // Marc est à jour partout.
        foreach ([$charte, $cgu] as $document) {
            MemberDocumentValidation::factory()->create([
                'internal_document_id' => $document->id,
                'user_id' => $marc->id,
                'version' => $document->version,
                'validated_at' => $this->today->subDays(2),
            ]);
        }
    }

    private function seedAdministrativeDocuments(Company $atelier, Company $studio): void
    {
        Storage::put('documents/demo-contrat-atelier.pdf', self::MINIMAL_PDF);
        Storage::put('documents/demo-domiciliation-atelier.pdf', self::MINIMAL_PDF);
        Storage::put('documents/demo-contrat-studio.pdf', self::MINIMAL_PDF);

        AdministrativeDocument::factory()->create([
            'company_id' => $atelier->id,
            'type' => AdministrativeDocumentType::Contract->value,
            'title' => 'Contrat de mise à disposition — 3 postes',
            'pdf_path' => 'documents/demo-contrat-atelier.pdf',
            'document_date' => $this->today->subMonths(8)->toDateString(),
            'uploaded_by' => $this->admin->id,
        ]);
        AdministrativeDocument::factory()->domiciliation()->create([
            'company_id' => $atelier->id,
            'pdf_path' => 'documents/demo-domiciliation-atelier.pdf',
            'document_date' => $this->today->subMonths(4)->toDateString(),
            'uploaded_by' => $this->admin->id,
        ]);
        AdministrativeDocument::factory()->create([
            'company_id' => $studio->id,
            'type' => AdministrativeDocumentType::Contract->value,
            'title' => 'Contrat de mise à disposition — 2 postes',
            'pdf_path' => 'documents/demo-contrat-studio.pdf',
            'document_date' => $this->today->subMonths(5)->toDateString(),
            'uploaded_by' => $this->admin->id,
        ]);
    }

    /** Paul : ancien résident parti (créé dans seedStudioVerger), compte anonymisé (RGPD §5.6). */
    private function seedAnonymizedMember(User $paul): void
    {
        app(AnonymizeUserService::class)->anonymize($paul, $this->admin);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** N-ième jour ouvré (L-V hors fériés) après (n > 0) ou avant (n < 0) aujourd'hui. */
    private function workingDay(int $n): CarbonImmutable
    {
        $date = $this->today;
        $step = $n >= 0 ? 1 : -1;
        $remaining = abs($n);

        while ($remaining > 0) {
            $date = $date->addDays($step);
            if (FrenchHolidays::isWorkingDay($date)) {
                $remaining--;
            }
        }

        return $date;
    }

    private function printAccounts(): void
    {
        $rows = [
            [self::ADMIN_EMAIL, 'admin', 'SEED_ADMIN_PASSWORD (ou affiché ci-dessus)', 'Back-office (2FA à configurer au 1er login)'],
            [self::CLAIRE_EMAIL, 'resident + billing_contact', self::PASSWORD, 'Atelier Lumière — factures, docs admin, charte v2 à revalider, bureau 1'],
            [self::MARC_EMAIL, 'resident', self::PASSWORD, 'Opt-out annuaire, docs à jour, bureau 2'],
            [self::INES_EMAIL, 'resident', self::PASSWORD, 'Absences récurrentes (vendredi) + congés, bureau 3'],
            [self::JULIEN_EMAIL, 'additional', self::PASSWORD, 'Sans bureau, sans accès factures'],
            [self::SOPHIE_EMAIL, 'resident + billing_contact', self::PASSWORD, 'Studio Verger — factures en retard (dont une avec acompte), bureau 30'],
            [self::KARIM_EMAIL, 'resident (pause)', self::PASSWORD, 'Abonnement en pause, bureau 31'],
            [self::THOMAS_EMAIL, 'external + billing_contact', self::PASSWORD, 'Tickets bureau/salle (dispo + utilisés), résa + occupation à venir, facture partielle'],
            [self::LEA_EMAIL, 'external', self::PASSWORD, 'Aucun ticket → états vides'],
            [self::CAMILLE_EMAIL, 'staff', self::PASSWORD, 'Équipe Ecoworking, bureau 29'],
            [self::PAUL_EMAIL, 'resident (anonymisé)', '—', 'Connexion impossible, factures conservées'],
        ];

        $this->command?->newLine();
        $this->command?->info('Comptes de démo (docs/recette.md §1) :');
        $this->command?->table(['Email', 'Rôle(s)', 'Mot de passe', 'Ce qu’on y voit'], $rows);
        $this->command?->warn('Jeu de données fictif — dev uniquement. Les emails partent dans Mailpit.');
    }
}
