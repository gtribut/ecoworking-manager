<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

it('résout le transport Brevo API quand le mailer brevo est utilisé (prod)', function () {
    config()->set('services.brevo.key', 'fake-brevo-key');

    $transport = Mail::mailer('brevo')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(BrevoApiTransport::class);
});
