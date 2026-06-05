<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Filament\Support\Contracts\HasLabel;

/** Type de document interne versionné (data_model §3, `internal_documents.type`). */
enum InternalDocumentType: string implements HasLabel
{
    use HasValues;

    case Charter = 'charter';
    case Cgu = 'cgu';
    case ImageRights = 'image_rights';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Charter => 'Charte',
            self::Cgu => 'CGU',
            self::ImageRights => 'Droit à l\'image',
            self::Other => 'Autre',
        };
    }
}
