<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Erreur métier d'une action portail (ticket indisponible, type incompatible,
 * bureau déjà occupé, absence chevauchante…). Traduite en HTTP 422 côté API.
 */
final class DomainActionException extends RuntimeException {}
