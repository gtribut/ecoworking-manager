<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AdministrativeDocumentController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AnnouncementRegistrationController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CalendarSubscriptionController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\DeskController;
use App\Http\Controllers\Api\DirectoryController;
use App\Http\Controllers\Api\InternalDocumentController;
use App\Http\Controllers\Api\InternalDocumentValidationController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API portail (portail.ecoworking.fr/api/*) — ADR-0003 / ADR-0004
|--------------------------------------------------------------------------
|
| Authentification par session Sanctum mode SPA : la SPA appelle d'abord
| GET /sanctum/csrf-cookie, se connecte via POST /login (Fortify), puis toutes
| les requêtes /api/* portent le cookie de session. `auth:sanctum` valide.
|
| CLAUDE.md §3.1 : TOUTES les routes portail passent par `auth:sanctum`.
| Le domaine est contraint via config('domains.portal') (prod `.fr` + dev local
| `.test`) ; nul en test → routes enregistrées sans contrainte (servies sur localhost).
|
*/

$register = function (): void {
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/user', CurrentUserController::class)->name('api.user');

        // C4.2 — Profil membre (lecture + édition partielle, auto-scopé).
        Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('api.profile.update');

        // C4.3 — Factures (liste scopée + téléchargement PDF).
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('api.invoices.index');
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('api.invoices.pdf');

        // C4.4 — Réservation de salle (catalogue, dispo, mes résas, annulation).
        Route::get('/rooms', [RoomController::class, 'index'])->name('api.rooms.index');
        Route::get('/rooms/{room}/availability', [RoomController::class, 'availability'])->name('api.rooms.availability');
        Route::get('/bookings', [BookingController::class, 'index'])->name('api.bookings.index');
        Route::post('/bookings', [BookingController::class, 'store'])->name('api.bookings.store');
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('api.bookings.destroy');

        // C4.5 — Tickets nomades, bureaux external & présence résident.
        Route::get('/tickets', [TicketController::class, 'index'])->name('api.tickets.index');
        Route::get('/desks/availability', [DeskController::class, 'availability'])->name('api.desks.availability');
        Route::post('/desk-occupations', [DeskController::class, 'store'])->name('api.desk-occupations.store');
        Route::delete('/desk-occupations/{deskOccupation}', [DeskController::class, 'destroy'])->name('api.desk-occupations.destroy');
        Route::get('/presence', [PresenceController::class, 'index'])->name('api.presence.index');
        Route::post('/absences', [PresenceController::class, 'store'])->name('api.absences.store');
        Route::delete('/absences/{absence}', [PresenceController::class, 'destroy'])->name('api.absences.destroy');

        // C12.3 — Annonces & événements (publiées + audience, RSVP auto-scopé).
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('api.announcements.index');
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->whereNumber('announcement')->name('api.announcements.show');
        Route::post('/announcements/{announcement}/registration', [AnnouncementRegistrationController::class, 'store'])->whereNumber('announcement')->name('api.announcements.registration.store');
        Route::delete('/announcements/{announcement}/registration', [AnnouncementRegistrationController::class, 'destroy'])->whereNumber('announcement')->name('api.announcements.registration.destroy');

        // C8.2 — Centre de notifications in-app (driver database, auto-scopé).
        Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');

        // C12.4 — Documents : internes à valider (audience) + administratifs d'entité (billing).
        Route::get('/documents/internal', [InternalDocumentController::class, 'index'])->name('api.documents.internal.index');
        Route::get('/documents/internal/{document}/pdf', [InternalDocumentController::class, 'downloadPdf'])->whereNumber('document')->name('api.documents.internal.pdf');
        Route::post('/documents/internal/{document}/validation', InternalDocumentValidationController::class)->whereNumber('document')->name('api.documents.internal.validate');
        Route::get('/documents/administrative', [AdministrativeDocumentController::class, 'index'])->name('api.documents.administrative.index');
        Route::get('/documents/administrative/{document}/pdf', [AdministrativeDocumentController::class, 'downloadPdf'])->whereNumber('document')->name('api.documents.administrative.pdf');

        // C12.5 — Annuaire des coworkers + plan des étages (profils opt-in,
        // permission view-annuaire : les external n'y accèdent pas, PRD §3.7.1).
        Route::get('/directory', [DirectoryController::class, 'index'])->name('api.directory.index');
        Route::get('/directory/floor-plan', [DirectoryController::class, 'floorPlan'])->name('api.directory.floor-plan');

        // C9.2 — Abonnement iCal (URLs de flux, régénération/révocation du token).
        Route::get('/calendar', [CalendarSubscriptionController::class, 'show'])->name('api.calendar.show');
        Route::post('/calendar/token', [CalendarSubscriptionController::class, 'regenerate'])->name('api.calendar.regenerate');
        Route::delete('/calendar/token', [CalendarSubscriptionController::class, 'destroy'])->name('api.calendar.destroy');
    });
};

if ($domain = config('domains.portal')) {
    Route::domain($domain)->group($register);
} else {
    $register();
}
