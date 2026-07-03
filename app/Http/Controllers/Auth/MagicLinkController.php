<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MagicLinkRequest;
use App\Services\Auth\MagicLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Connexion membre par magic link (C12.8a — PRD §3.2, ADR-0011).
 *
 * Domaine portail uniquement. La logique (éligibilité, jeton, email) vit dans
 * MagicLinkService ; ce controller ne fait que l'orchestration HTTP.
 */
final class MagicLinkController extends Controller
{
    public function __construct(private readonly MagicLinkService $magicLinks) {}

    /**
     * Demande d'un lien de connexion. Réponse STRICTEMENT identique que l'email
     * corresponde ou non à un compte éligible (anti-énumération, PRD §3.2).
     */
    public function store(MagicLinkRequest $request): JsonResponse
    {
        $this->magicLinks->request($request->validated('email'));

        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un lien de connexion vient d\'être envoyé par email.',
        ]);
    }

    /**
     * Consommation du lien : signature d'URL valide + jeton non utilisé/non
     * expiré + compte éligible → session ouverte (guard web, comme Fortify) et
     * redirection vers la SPA. Tout échec → retour login avec un indicateur
     * générique (aucun détail exploitable).
     */
    public function consume(Request $request, string $token): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->deny();
        }

        $user = $this->magicLinks->consume($token);

        if ($user === null) {
            return $this->deny();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->to(config('fortify.home', '/'));
    }

    private function deny(): RedirectResponse
    {
        return redirect()->to('/login?magic_link=invalid');
    }
}
