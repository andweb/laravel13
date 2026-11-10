<?php

declare(strict_types=1);

namespace App\Actions\AiContent;

use App\Enums\AiGenerationKind;
use App\Enums\AiGenerationStatus;
use App\Jobs\GenerateAiContent;
use App\Models\AiGeneration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestStatement
{
    public function handle(User $user, string $key, string $problem): AiGeneration
    {
        return DB::transaction(function () use ($user, $key, $problem): AiGeneration {
            $generation = AiGeneration::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'idempotency_key' => $key,
                ],
                [
                    'kind' => AiGenerationKind::Statement,
                    'status' => AiGenerationStatus::Pending,
                    'request' => ['problem' => $problem],
                ],
            );

            $stored = $generation->request['problem'] ?? null;

            if ($stored !== $problem) {
                throw ValidationException::withMessages([
                    'problem' => 'This Idempotency-Key was already used with a different problem.',
                ]);
            }

            if ($generation->wasRecentlyCreated) {
                GenerateAiContent::dispatch($generation)->afterCommit();
            }

            return $generation;
        });
    }
}
