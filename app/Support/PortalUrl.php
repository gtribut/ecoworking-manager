<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Construction des URLs absolues vers le portail membre (ADR-0004).
 *
 * Source de vérité unique : `config('domains.portal')` — **jamais** `app.url`,
 * qui pointe le domaine ADMIN (`APP_URL=https://admin.ecoworking.fr`). Les
 * liens d'emails de notification (« Voir mes documents », « Voir mes
 * factures »…) partaient sur `admin.ecoworking.fr`, inaccessible aux membres.
 * `app.url` ne sert plus qu'à déduire le schéma (http en dev, https en prod).
 *
 * En contexte de test le domaine portail est nul (phpunit.xml vide
 * ADMIN/PORTAL_DOMAIN, cf. ADR-0004) : on retombe sur `url()`.
 */
final class PortalUrl
{
    public static function to(string $path): string
    {
        $domain = config('domains.portal');

        if (! is_string($domain) || $domain === '') {
            return url($path);
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$domain.'/'.ltrim($path, '/');
    }
}
