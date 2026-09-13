<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskAbsences\Pages;

use App\Filament\Resources\DeskAbsences\DeskAbsenceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeskAbsences extends ListRecords
{
    protected static string $resource = DeskAbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
