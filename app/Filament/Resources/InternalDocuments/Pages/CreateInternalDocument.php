<?php

declare(strict_types=1);

namespace App\Filament\Resources\InternalDocuments\Pages;

use App\Filament\Resources\InternalDocuments\InternalDocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateInternalDocument extends CreateRecord
{
    protected static string $resource = InternalDocumentResource::class;

    /**
     * Trace l'admin auteur du document commun (data_model §4.5, `created_by`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
