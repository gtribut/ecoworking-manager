<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Anti-énumération sur « mot de passe oublié » (PRD §3.2, recette R-03).
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
    }

    /**
     * Bootstrap any application services.
     *
     * Pas de `createUsersUsing` : l'inscription self-service est désactivée
     * (PRD §3.2 — comptes créés par l'admin via Filament).
     */
    public function boot(): void
    {
        // Pas de `updateUserProfileInformationUsing` : feature désactivée (le
        // scaffold écrivait une colonne `name` inexistante — le profil passe
        // par /api/profile, PRD §3.4.5).
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Lien de réinitialisation → page de la SPA portail (PRD §3.2, R-03).
        // Fortify est en `views => false` : la route GET `password.reset` n'existe
        // pas, le lien doit donc être construit explicitement vers le portail.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => self::portalUrl(
            '/reset-password/'.$token.'?email='.urlencode($user->email),
        ));

        // Rate limiting login : 5 tentatives/min par (email|IP) — PRD §3.2 / BRIEF §8.
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Rate limiting magic link (C12.8a, PRD §3.2) : même clé que le login
        // (email|IP) — limite le spam d'emails et le harcèlement d'un compte.
        RateLimiter::for('magic-link', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * URL absolue sur le domaine portail (config/domains, ADR-0004). En test
     * (domaine nul) : URL locale classique. Le schéma suit APP_URL (https en prod).
     */
    public static function portalUrl(string $path): string
    {
        $domain = config('domains.portal');

        if (! is_string($domain) || $domain === '') {
            return url($path);
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$domain.'/'.ltrim($path, '/');
    }
}
