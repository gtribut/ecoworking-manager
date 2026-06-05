<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AdministrativeDocument;
use App\Models\Announcement;
use App\Models\AnnouncementRegistration;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\DeskAbsence;
use App\Models\DeskOccupation;
use App\Models\InternalDocument;
use App\Models\Invoice;
use App\Models\InvoiceCounter;
use App\Models\InvoiceLine;
use App\Models\MemberDocumentValidation;
use App\Models\MemberProfile;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Resource;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Chaque factory doit produire une ligne valide en base (respect des CHECK
 * enums, FK et contraintes critiques). Couvre l'ensemble des entités C1.5.
 *
 * @return list<class-string<Model>>
 */
dataset('models', [
    User::class,
    Company::class,
    MemberProfile::class,
    Contact::class,
    Consent::class,
    Offer::class,
    Subscription::class,
    Purchase::class,
    Ticket::class,
    Resource::class,
    Booking::class,
    DeskOccupation::class,
    DeskAbsence::class,
    Invoice::class,
    InvoiceLine::class,
    Payment::class,
    InvoiceCounter::class,
    Announcement::class,
    AnnouncementRegistration::class,
    InternalDocument::class,
    MemberDocumentValidation::class,
    AdministrativeDocument::class,
]);

it('produit une ligne persistée et valide', function (string $model) {
    $instance = $model::factory()->create();

    expect($instance->exists)->toBeTrue()
        ->and($model::whereKey($instance->getKey())->exists())->toBeTrue();
})->with('models');

it('assigne le rôle via les traits du UserFactory', function () {
    $admin = User::factory()->admin()->create();
    $resident = User::factory()->resident()->create();

    expect($admin->hasRole(RoleEnum::Admin->value))->toBeTrue()
        ->and($resident->hasRole(RoleEnum::Resident->value))->toBeTrue()
        ->and($resident->hasRole(RoleEnum::Admin->value))->toBeFalse();
});

it('respecte les états métier des factories', function () {
    expect(Company::factory()->individual()->create()->entity_type->value)->toBe('individual')
        ->and(Resource::factory()->meetingRoom()->create()->type->value)->toBe('meeting_room')
        ->and(Invoice::factory()->issued()->create()->number)->not->toBeNull()
        ->and(Offer::factory()->subscription()->create()->billing_period->value)->toBe('monthly');
});

it('honore la domiciliation unique par entité (index §6.3)', function () {
    $company = Company::factory()->create();
    Subscription::factory()->domiciliation($company)->create();

    expect($company->subscriptionsAsSubscriber()->count())->toBe(1);
});
