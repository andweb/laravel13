<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AiGenerationKind;
use App\Models\AiGeneration;
use App\Models\User;

class AiGenerationPolicy
{
    public function view(User $user, AiGeneration $aiGeneration): bool
    {
        return $aiGeneration->kind === AiGenerationKind::Statement
            && (int) $aiGeneration->user_id === $user->id;
    }
}
