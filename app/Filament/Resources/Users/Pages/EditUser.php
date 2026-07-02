<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            // Pas de ForceDeleteAction : UserPolicy::forceDelete() est `false`
            // en dur (RGPD : soft delete + anonymisation uniquement).
            RestoreAction::make(),
        ];
    }
}
