<?php

declare(strict_types=1);

use App\Enums\CompanyType;
use App\Enums\InvoiceStatus;
use App\Enums\OfferType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Offer;
use App\Models\User;

it('cast les colonnes enum vers leur enum PHP', function () {
    $offer = Offer::create([
        'code' => 'TEST-NOMADE',
        'name' => 'Ticket nomade',
        'type' => OfferType::OneShot->value,
        'unit_price_ht' => '15.00',
    ]);

    expect($offer->refresh()->type)->toBe(OfferType::OneShot)
        ->and($offer->unit_price_ht)->toBe('15.00'); // decimal:2 conserve la précision
});

it('résout la relation 1-1 user ↔ member_profile dans les deux sens', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'entity_type' => CompanyType::Company->value,
        'legal_name' => 'Acme SCOP',
    ]);

    $profile = MemberProfile::create([
        'user_id' => $user->id,
        'company_id' => $company->id,
    ]);

    expect($user->memberProfile->is($profile))->toBeTrue()
        ->and($profile->user->is($user))->toBeTrue()
        ->and($profile->company->is($company))->toBeTrue();
});

it('résout le billable polymorphe via la morph map (alias court)', function () {
    $company = Company::create([
        'entity_type' => CompanyType::Company->value,
        'legal_name' => 'Acme SCOP',
    ]);

    $invoice = Invoice::create([
        'billable_type' => 'company', // alias morph map, pas le FQCN
        'billable_id' => $company->id,
        'status' => InvoiceStatus::Draft->value,
    ]);

    expect($invoice->getRawOriginal('billable_type'))->toBe('company')
        ->and($invoice->billable)->toBeInstanceOf(Company::class)
        ->and($invoice->billable->is($company))->toBeTrue()
        ->and($company->invoices->first()->is($invoice))->toBeTrue();
});

it('applique le scope local active sur les entités', function () {
    $active = Company::create(['entity_type' => CompanyType::Company->value, 'legal_name' => 'Active']);
    Company::create(['entity_type' => CompanyType::Company->value, 'legal_name' => 'Inactive', 'status' => 'inactive']);

    expect(Company::active()->pluck('id')->all())->toBe([$active->id]);
});
