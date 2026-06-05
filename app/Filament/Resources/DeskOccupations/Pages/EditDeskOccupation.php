<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskOccupations\Pages;

use App\Filament\Resources\DeskOccupations\DeskOccupationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeskOccupation extends EditRecord
{
    protected static string $resource = DeskOccupationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
