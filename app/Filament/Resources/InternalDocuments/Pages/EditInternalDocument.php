<?php

declare(strict_types=1);

namespace App\Filament\Resources\InternalDocuments\Pages;

use App\Filament\Resources\InternalDocuments\InternalDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInternalDocument extends EditRecord
{
    protected static string $resource = InternalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
