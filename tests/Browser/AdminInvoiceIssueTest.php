<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

it('émet un brouillon : numéro chronologique attribué et PDF accessible', function () {
    Storage::fake();
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = createAdminWithTotp();

    // Brouillon avec une ligne (les brouillons ne consomment jamais le compteur).
    $invoice = Invoice::factory()->create();
    InvoiceLine::factory()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Pack 10 tickets bureau',
        'quantity' => 1,
        'unit_price_ht' => 140.00,
        'vat_rate' => 20.00,
        'line_total_ht' => 140.00,
        'line_vat' => 28.00,
        'line_total_ttc' => 168.00,
    ]);
    expect($invoice->number)->toBeNull();

    $page = loginToAdminPanel($admin);

    $page->navigate("/admin/invoices/{$invoice->id}/edit")
        ->waitForText('Émettre la facture')
        ->click('Émettre la facture')
        // Confirmation forte : émission irréversible (CGI art. 289).
        ->waitForText('Le numéro sera attribué définitivement')
        ->click('Confirmer')
        ->waitForText('Facture émise');

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->number)->toBe('EW-'.now()->year.'-00001')
        ->and((float) $invoice->total_ttc)->toBe(168.00);

    // Redirection vers la page de consultation : numéro affiché (valeur du
    // champ, pas un nœud texte) et seule l'annulation + avoir reste possible.
    $page->waitForText('Totaux figés')
        ->assertPathContains("/admin/invoices/{$invoice->id}")
        ->assertValue('input[id$="number"]', $invoice->number)
        ->assertSee("Annuler + générer l'avoir");

    // PDF généré en synchrone (queue sync) et téléchargeable (flux argent).
    $this->actingAs($admin)
        ->get("/api/invoices/{$invoice->id}/pdf")
        ->assertOk()
        ->assertDownload("{$invoice->number}.pdf");
});
