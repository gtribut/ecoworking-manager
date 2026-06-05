<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskOccupations\Pages;

use App\Filament\Resources\DeskOccupations\DeskOccupationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeskOccupations extends ListRecords
{
    protected static string $resource = DeskOccupationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
