<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberProfiles\Pages;

use App\Filament\Resources\MemberProfiles\MemberProfileResource;
use App\Models\MemberProfile;
use App\Services\Profile\ProfilePhotoService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditMemberProfile extends EditRecord
{
    protected static string $resource = MemberProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Modération : l'admin ne téléverse plus de photo (le membre le
            // fait depuis son portail, PRD §3.4.2) mais peut en retirer une —
            // même pipeline de suppression que le portail et que l'anonymisation.
            Action::make('deletePhoto')
                ->label('Retirer la photo')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (MemberProfile $record): bool => $record->photo_path !== null)
                ->requiresConfirmation()
                ->modalHeading('Retirer la photo de ce membre ?')
                ->modalDescription('Les trois rendus (80, 200 et 400 px) seront supprimés du stockage. Le membre pourra en déposer une nouvelle depuis son portail.')
                ->action(function (MemberProfile $record, ProfilePhotoService $photos): void {
                    $photos->delete($record);
                })
                ->successNotificationTitle('Photo retirée')
                ->after(fn (EditMemberProfile $livewire) => $livewire->refreshFormData(['photo_path'])),

            DeleteAction::make(),
        ];
    }
}
