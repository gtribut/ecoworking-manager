<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberProfiles\Schemas;

use App\Enums\MemberProfileStatus;
use App\Enums\ResourceType;
use App\Models\Company;
use App\Models\MemberProfile;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MemberProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rattachement')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Compte utilisateur')
                            ->relationship('user', 'email')
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->fullName()} ({$record->email})")
                            ->searchable()
                            ->preload()
                            ->required()
                            // 1-1 : un compte n'a qu'un profil (unicité `user_id`).
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit'),
                        Select::make('company_id')
                            ->label('Entité de rattachement')
                            ->relationship('company')
                            ->getOptionLabelFromRecordUsing(fn (Company $record): string => $record->name)
                            ->searchable()
                            ->preload(),
                        Select::make('status')
                            ->label('Statut')
                            ->options(MemberProfileStatus::class)
                            ->required()
                            ->default(MemberProfileStatus::Active->value),
                        Select::make('desk_id')
                            ->label('Bureau attitré')
                            ->relationship(
                                'desk',
                                'name',
                                fn (Builder $query): Builder => $query->where('type', ResourceType::Desk->value),
                            )
                            ->searchable()
                            ->preload()
                            // 1-1 bureau ↔ membre (§6.10).
                            ->unique(ignoreRecord: true)
                            ->helperText('Un bureau ne peut être attribué qu\'à un seul membre.'),
                    ]),

                Section::make('Période de présence')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('arrival_date')
                            ->label('Date d\'arrivée'),
                        DatePicker::make('departure_date')
                            ->label('Date de départ')
                            ->after('arrival_date'),
                    ]),

                Section::make('Profil public (annuaire)')
                    ->columns(2)
                    ->schema([
                        // Photo : déposée par le membre depuis son portail
                        // (PRD §3.4.2 — recadrage et EXIF traités côté serveur
                        // par ProfilePhotoService). L'admin ne la téléverse
                        // pas : il peut seulement la retirer (modération), via
                        // l'action d'en-tête de la page d'édition.
                        Placeholder::make('photo_state')
                            ->label('Photo')
                            ->content(fn (?MemberProfile $record): string => $record?->photo_path === null
                                ? 'Aucune photo. Le membre la dépose depuis son portail.'
                                : 'Photo déposée par le membre. Utilisez « Retirer la photo » pour la supprimer.')
                            ->columnSpanFull(),
                        TextInput::make('job_title')
                            ->label('Intitulé de poste')
                            ->maxLength(150),
                        DatePicker::make('birth_date')
                            ->label('Date de naissance')
                            ->maxDate('today'),
                        Textarea::make('bio')
                            ->label('Bio')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('interests')
                            ->label('Centres d\'intérêt')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('linkedin_url')
                            ->label('LinkedIn')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('website_url')
                            ->label('Site web')
                            ->url()
                            ->maxLength(255),
                        Toggle::make('show_in_directory')
                            ->label('Visible dans l\'annuaire')
                            ->helperText('Opt-in explicite (RGPD).'),
                        Toggle::make('newsletter_opt_in')
                            ->label('Inscrit à la newsletter'),
                    ]),

                Section::make('Notes internes')
                    ->collapsed()
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label('Notes admin')
                            ->rows(3),
                    ]),
            ]);
    }
}
