<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // ADR-0004 : en prod (`.fr`) et en dev local (`.test`) le panel est
        // contraint au sous-domaine admin (chemin racine `/`). En test
        // `ADMIN_DOMAIN` est forcé nul (phpunit.xml) → panel servi sur `/admin`
        // sans contrainte de domaine (pas de setup /etc/hosts pour les tests).
        $adminDomain = config('domains.admin');

        return $panel
            ->default()
            ->id('admin')
            ->path($adminDomain ? '' : 'admin')
            ->when($adminDomain, fn (Panel $panel): Panel => $panel->domain($adminDomain))
            ->brandName('Ecoworking')
            ->login()
            // 2FA TOTP obligatoire pour les admins (C3.1) : setup forcé au 1er
            // login + challenge à chaque connexion, codes de récupération inclus.
            // Mécanisme natif Filament, distinct du 2FA Fortify du portail membre.
            ->multiFactorAuthentication(
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName('Ecoworking'),
                isRequired: true,
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
