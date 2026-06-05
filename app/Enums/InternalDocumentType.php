<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Type de document interne versionné (data_model §3, `internal_documents.type`). */
enum InternalDocumentType: string
{
    use HasValues;

    case Charter = 'charter';
    case Cgu = 'cgu';
    case ImageRights = 'image_rights';
    case Other = 'other';
}
