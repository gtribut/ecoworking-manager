<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLog\Pages;

use App\Filament\Resources\ActivityLog\ActivityResource;
use Filament\Resources\Pages\ViewRecord;

/** Détail d'une entrée d'audit (diff avant/après) — lecture seule. */
class ViewActivity extends ViewRecord
{
    protected static string $resource = ActivityResource::class;
}
