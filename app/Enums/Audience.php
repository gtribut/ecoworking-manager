<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/**
 * Audience / visibilité (data_model §3).
 * Utilisé par `announcements.visibility` et `internal_documents.audience`.
 */
enum Audience: string implements HasLabel
{
    use HasValues;

    case All = 'all';
    case Residents = 'residents';
    case Additional = 'additional';
    case BillingContact = 'billing_contact';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Tous',
            self::Residents => 'Résidents',
            self::Additional => 'Membres additionnels',
            self::BillingContact => 'Contacts facturation',
        };
    }

    /**
     * Rôles couverts par l'audience (C12.3). Source unique de la règle
     * « qui voit quoi » — partagée entre le scope de lecture portail
     * (Announcement::scopeVisibleTo) et le ciblage des notifications de
     * publication (AnnouncementObserver).
     *
     * - `all` = tous les rôles portail (les rôles d'usage + billing_contact).
     *   Les admins purs passent par le court-circuit isAdmin, pas par ici.
     * - `residents` inclut `staff` : PRD §2.5, « les staff ont des droits
     *   équivalents à un résident côté portail ».
     *
     * @return list<Role>
     */
    public function roles(): array
    {
        return match ($this) {
            self::All => [Role::Resident, Role::Additional, Role::External, Role::Staff, Role::BillingContact],
            self::Residents => [Role::Resident, Role::Staff],
            self::Additional => [Role::Additional],
            self::BillingContact => [Role::BillingContact],
        };
    }

    /**
     * Valeurs de rôle (strings spatie) couvertes par l'audience.
     *
     * @return list<string>
     */
    public function roleValues(): array
    {
        return array_map(static fn (Role $role): string => $role->value, $this->roles());
    }
}
