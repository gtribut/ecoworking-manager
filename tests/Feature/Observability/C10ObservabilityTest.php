<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * C10 — Observabilité. Vérifie le contrôle d'accès au dashboard Pulse et la
 * disponibilité de l'endpoint santé monitoré par Better Stack.
 */
it('réserve le dashboard Pulse aux admins (gate viewPulse)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->resident()->create();

    expect(Gate::forUser($admin)->allows('viewPulse'))->toBeTrue()
        ->and(Gate::forUser($member)->allows('viewPulse'))->toBeFalse();
});

it('expose un endpoint santé /up pour le monitoring uptime', function () {
    $this->get('/up')->assertOk();
});
