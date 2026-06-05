<?php

declare(strict_types=1);

use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Contacts\Pages\CreateContact;
use App\Filament\Resources\Contacts\Pages\EditContact;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Filament\Resources\MemberProfiles\Pages\CreateMemberProfile;
use App\Filament\Resources\MemberProfiles\Pages\EditMemberProfile;
use App\Filament\Resources\MemberProfiles\Pages\ListMemberProfiles;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Company;
use App\Models\Contact;
use App\Models\MemberProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C3.2 — Resources Filament (User, MemberProfile, Company, Contact).
 *
 * Rendu testé au niveau composant Livewire (approche Filament) : on exerce les
 * schémas form/table sans le middleware HTTP du panel (auth + 2FA obligatoire,
 * couverts par FilamentPanelTest). L'autorisation reste appliquée au mount via
 * les Policies. L'isolation admin-only est vérifiée ici + dans ResourcePoliciesTest.
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

dataset('pages', [
    'comptes' => [ListUsers::class, CreateUser::class, EditUser::class, fn () => User::factory()->create()],
    'entités' => [ListCompanies::class, CreateCompany::class, EditCompany::class, fn () => Company::factory()->create()],
    'profils' => [ListMemberProfiles::class, CreateMemberProfile::class, EditMemberProfile::class, fn () => MemberProfile::factory()->create()],
    'contacts' => [ListContacts::class, CreateContact::class, EditContact::class, fn () => Contact::factory()->create()],
]);

it('rend les pages list / create / edit pour un admin', function (string $list, string $create, string $edit, callable $make) {
    actingAs(User::factory()->admin()->create());

    Livewire::test($list)->assertOk();
    Livewire::test($create)->assertOk();

    $record = $make();
    Livewire::test($edit, ['record' => $record->getRouteKey()])->assertOk();
})->with('pages');

it('refuse à un membre la liste des comptes (UserPolicy::viewAny)', function () {
    actingAs(User::factory()->member()->create());

    Livewire::test(ListUsers::class)->assertForbidden();
});

it('refuse à un membre la liste des contacts (ContactPolicy::viewAny)', function () {
    actingAs(User::factory()->member()->create());

    Livewire::test(ListContacts::class)->assertForbidden();
});
