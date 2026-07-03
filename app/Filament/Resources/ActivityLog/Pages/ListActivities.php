<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLog\Pages;

use App\Filament\Resources\ActivityLog\ActivityResource;
use Filament\Resources\Pages\ListRecords;

/** Liste de l'audit log — lecture seule, aucune action d'en-tête. */
class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;
}
