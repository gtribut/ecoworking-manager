<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MemberDocumentValidation;
use App\Models\User;

/** Isolation des validations de documents internes (PRD §2.5, Q18 résolue). */
final class MemberDocumentValidationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MemberDocumentValidation $validation): bool
    {
        return $user->isAdmin() || $user->id === $validation->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->can(Permission::ValidateInternalDocument->value);
    }
}
