<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nature d'un changement notifié au membre par le back-office (PRD §3.8.4 :
 * « résa confirmée (création/modification/suppression admin) », « absence
 * enregistrée par l'admin »). Purement applicatif : aucune colonne en base,
 * seulement la clé du libellé porté par la notification.
 */
enum NotifiedAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Removed = 'removed';
}
