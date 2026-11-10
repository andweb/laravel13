<?php

declare(strict_types=1);

namespace App\Http\Controllers\AiContent;

use App\Actions\AiContent\RequestStatement;
use App\Enums\AiGenerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiContent\StoreStatementRequest;
use App\Http\Resources\AiGenerationResource;
use App\Models\AiGeneration;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class StatementController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreStatementRequest $request, RequestStatement $action): JsonResponse
    {
        $problem = $request->validated('problem');

        if (! is_string($problem)) {
            throw new \RuntimeException('Problem is missing.');
        }

        $user = $request->user();

        if (! $user instanceof User) {
            throw new \RuntimeException('User is missing.');
        }

        $generation = $action->handle($user, $request->idempotencyKey(), $problem);

        return $this->response($generation);
    }

    public function show(AiGeneration $aiGeneration): JsonResponse
    {
        $this->authorize('view', $aiGeneration);

        return $this->response($aiGeneration);
    }

    private function response(AiGeneration $generation): JsonResponse
    {
        $response = (new AiGenerationResource($generation))->response();

        return match ($generation->status) {
            AiGenerationStatus::Pending => $response
                ->setStatusCode(202)
                ->header('Location', route('ai-content.statements.show', $generation))
                ->header('Retry-After', '2'),
            AiGenerationStatus::Done => $response->setStatusCode(200),
            AiGenerationStatus::Failed => $response->setStatusCode(502),
        };
    }
}
