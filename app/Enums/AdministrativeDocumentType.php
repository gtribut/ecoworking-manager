<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/** Type de document administratif d'entité (data_model §3, `administrative_documents.type`). */
enum AdministrativeDocumentType: string implements HasLabel
{
    use HasValues;

    case Contract = 'contract';
    case Amendment = 'amendment';
    case Domiciliation = 'domiciliation';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Contract => 'Contrat',
            self::Amendment => 'Avenant',
            self::Domiciliation => 'Domiciliation',
            self::Other => 'Autre',
        };
    }
}
