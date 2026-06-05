<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Permissions granulaires (spatie/laravel-permission), PRD §2.8.
 *
 * Les rôles (enum Role) sont des compositions de ces permissions, figées dans
 * le PermissionSeeder. Les Policies Eloquent vérifient ces permissions
 * (PRD §2.7) en plus de l'isolation par propriété.
 */
enum Permission: string
{
    use HasValues;

    // --- Portail ---------------------------------------------------------
    case ViewAnnuaire = 'view-annuaire';
    case ViewOwnBookings = 'view-own-bookings';
    case ViewBookingsCalendar = 'view-bookings-calendar';
    case CreateOwnBooking = 'create-own-booking';
    case CreatePaidBooking = 'create-paid-booking';
    case ManageOwnBooking = 'manage-own-booking';
    case RegisterEvent = 'register-event';
    case ValidateInternalDocument = 'validate-internal-document';
    case ViewBillingSection = 'view-billing-section';
    case ViewEntityInvoices = 'view-entity-invoices';
    case ViewEntityAdminDocuments = 'view-entity-admin-documents';
    case RequestEntityModification = 'request-entity-modification';
    case PurchaseNomadTickets = 'purchase-nomad-tickets';

    // --- Admin (back-office) --------------------------------------------
    case ManageMembers = 'manage-members';
    case ManageCompanies = 'manage-companies';
    case ManageContacts = 'manage-contacts';
    case ManageOffers = 'manage-offers';
    case ManageSubscriptions = 'manage-subscriptions';
    case ManageResources = 'manage-resources';
    case ManageBookings = 'manage-bookings';
    case ManageBookingsForOthers = 'manage-bookings-for-others';
    case ManageInvoices = 'manage-invoices';
    case ManagePayments = 'manage-payments';
    case ManageInternalDocuments = 'manage-internal-documents';
    case ManageAdminDocuments = 'manage-admin-documents';
    case ManageAnnouncements = 'manage-announcements';
    case ManageDeskAssignments = 'manage-desk-assignments';
    case ManageRoles = 'manage-roles';
    case ViewAuditLog = 'view-audit-log';
    case ManageSettings = 'manage-settings';
    case ConsumeNomadTicketsForOthers = 'consume-nomad-tickets-for-others';
    case DeclarePresenceForOthers = 'declare-presence-for-others';

    /**
     * Permissions du module back-office admin (toutes attribuées au rôle `admin`).
     *
     * @return list<self>
     */
    public static function adminPermissions(): array
    {
        return [
            self::ManageMembers, self::ManageCompanies, self::ManageContacts,
            self::ManageOffers, self::ManageSubscriptions, self::ManageResources,
            self::ManageBookings, self::ManageBookingsForOthers, self::ManageInvoices,
            self::ManagePayments, self::ManageInternalDocuments, self::ManageAdminDocuments,
            self::ManageAnnouncements, self::ManageDeskAssignments, self::ManageRoles,
            self::ViewAuditLog, self::ManageSettings, self::ConsumeNomadTicketsForOthers,
            self::DeclarePresenceForOthers,
        ];
    }
}
