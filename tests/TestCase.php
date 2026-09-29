<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La base `testing` est isolée, mais pas le disque : sans fake, un test
        // qui émet EW-2026-00001 écrase le PDF de dev au même chemin (R-11).
        // Filet global — un test peut toujours re-fake explicitement.
        Storage::fake('local');
        Storage::fake('public');
    }
}
