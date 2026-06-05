<?php

declare(strict_types=1);

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\Audience;
use App\Filament\Resources\AdministrativeDocuments\Pages\CreateAdministrativeDocument;
use App\Filament\Resources\AdministrativeDocuments\Pages\EditAdministrativeDocument;
use App\Filament\Resources\AdministrativeDocuments\Pages\ListAdministrativeDocuments;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\InternalDocuments\Pages\CreateInternalDocument;
use App\Filament\Resources\InternalDocuments\Pages\EditInternalDocument;
use App\Filament\Resources\InternalDocuments\Pages\ListInternalDocuments;
use App\Models\AdministrativeDocument;
use App\Models\Announcement;
use App\Models\InternalDocument;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * C3.6 — Resources communication & documents (Announcement, InternalDocument,
 * AdministrativeDocument). Rendu testé au niveau composant Livewire.
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

dataset('communicationPages', [
    'annonces' => [ListAnnouncements::class, CreateAnnouncement::class, EditAnnouncement::class, fn () => Announcement::factory()->create()],
    'documents communs' => [ListInternalDocuments::class, CreateInternalDocument::class, EditInternalDocument::class, fn () => InternalDocument::factory()->create()],
    'documents entités' => [ListAdministrativeDocuments::class, CreateAdministrativeDocument::class, EditAdministrativeDocument::class, fn () => AdministrativeDocument::factory()->create()],
]);

it('rend les pages list / create / edit pour un admin', function (string $list, string $create, string $edit, callable $make) {
    Livewire::test($list)->assertOk();
    Livewire::test($create)->assertOk();

    $record = $make();
    Livewire::test($edit, ['record' => $record->getRouteKey()])->assertOk();
})->with('communicationPages');

it('réserve les annonces et documents communs à l\'admin', function () {
    $member = User::factory()->member()->create();

    expect($member->can('viewAny', Announcement::class))->toBeFalse()
        ->and($member->can('viewAny', InternalDocument::class))->toBeFalse();
});

it('refuse à un membre la liste des annonces', function () {
    actingAs(User::factory()->member()->create());

    Livewire::test(ListAnnouncements::class)->assertForbidden();
});

it('trace l\'admin rédacteur sur une annonce back-office', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    Livewire::test(CreateAnnouncement::class)
        ->fillForm([
            'type' => AnnouncementType::Info->value,
            'visibility' => Audience::All->value,
            'title' => 'Fermeture exceptionnelle',
            'body' => 'Le coworking sera fermé le 1er mai.',
            'status' => AnnouncementStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Announcement::firstOrFail()->created_by)->toBe($admin->id);
});
