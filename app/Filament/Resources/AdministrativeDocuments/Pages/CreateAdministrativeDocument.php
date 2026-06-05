<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdministrativeDocuments\Pages;

use App\Filament\Resources\AdministrativeDocuments\AdministrativeDocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAdministrativeDocument extends CreateRecord
{
    protected static string $resource = AdministrativeDocumentResource::class;

    /**
     * Trace l'admin ayant déposé le document (data_model §4.5, `uploaded_by`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = Auth::id();

        return $data;
    }
}
