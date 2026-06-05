<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdministrativeDocuments\Pages;

use App\Filament\Resources\AdministrativeDocuments\AdministrativeDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdministrativeDocuments extends ListRecords
{
    protected static string $resource = AdministrativeDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
