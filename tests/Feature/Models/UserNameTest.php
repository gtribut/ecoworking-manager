<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Models\Contracts\HasName;

it('expose le nom complet à Filament via getFilamentName()', function () {
    $user = User::factory()->create([
        'first_name' => 'Admin',
        'last_name' => 'Ecoworking',
    ]);

    expect($user)->toBeInstanceOf(HasName::class)
        ->and($user->getFilamentName())->toBe('Admin Ecoworking');
});

it('ne renvoie jamais null à Filament même sans last_name', function () {
    $user = User::factory()->create([
        'first_name' => 'Solo',
        'last_name' => '',
    ]);

    expect($user->getFilamentName())->toBe('Solo')
        ->and($user->getFilamentName())->toBeString();
});
