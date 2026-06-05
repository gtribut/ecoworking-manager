<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Type de document administratif d'entité (data_model §3, `administrative_documents.type`). */
enum AdministrativeDocumentType: string
{
    use HasValues;

    case Contract = 'contract';
    case Amendment = 'amendment';
    case Domiciliation = 'domiciliation';
    case Other = 'other';
}
