<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/**
 * Rôles applicatifs (Spatie laravel-permission), data_model §3.
 *
 * `resident`, `additional`, `external`, `staff` sont des rôles d'usage
 * mutuellement exclusifs (XOR, contrainte §6.1, niveau applicatif).
 * `admin` et `billing_contact` se cumulent librement.
 */
enum Role: string implements HasLabel
{
    use HasValues;

    case Admin = 'admin';
    case Resident = 'resident';
    case Additional = 'additional';
    case External = 'external';
    case Staff = 'staff';
    case BillingContact = 'billing_contact';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Resident => 'Résident',
            self::Additional => 'Membre additionnel',
            self::External => 'Externe (nomade)',
            self::Staff => 'Équipe Ecoworking',
            self::BillingContact => 'Contact facturation',
        };
    }

    /**
     * Rôles d'usage mutuellement exclusifs (un au plus par user).
     *
     * @return list<self>
     */
    public static function usageRoles(): array
    {
        return [self::Resident, self::Additional, self::External, self::Staff];
    }
}
