<?php

declare(strict_types=1);

use App\Filament\Resources\AdministrativeDocuments\Pages\ListAdministrativeDocuments;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Filament\Resources\MemberProfiles\Pages\ListMemberProfiles;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Régression : le filtre « Entité » des listes admin préchargeait les options
 * avec `legal_name` — nul pour un particulier → TypeError Filament (500) dès
 * qu'une entité `individual` existait en base (constaté sur la base de démo).
 */
beforeEach(function () {
    Filament::setCurrentPanel('admin');
    actingAs(User::factory()->admin()->create());

    Company::factory()->individual()->create(['first_name' => 'Thomas', 'last_name' => 'Bernard']);
});

it('affiche les listes filtrables par entité avec un particulier en base', function (string $list) {
    Livewire::test($list)->assertOk()->assertSee('Thomas Bernard');
})->with([
    'documents entités' => ListAdministrativeDocuments::class,
    'contacts' => ListContacts::class,
    'profils membres' => ListMemberProfiles::class,
]);
