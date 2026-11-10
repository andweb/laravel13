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
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class StatementController extends Controller
{
    use AuthorizesRequests;

    #[Response(status: 202, description: 'The statement is still being generated.', type: AiGenerationResource::class)]
    #[Response(status: 200, description: 'The statement text is ready.', type: AiGenerationResource::class)]
    #[Response(status: 502, description: 'The model failed to generate the statement.', type: AiGenerationResource::class)]
    #[Header(name: 'Retry-After', description: 'Seconds before the next poll.', status: 202, type: 'string', example: '2')]
    #[Header(name: 'Location', description: 'URL of the statement.', status: 202, type: 'string')]
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

    #[Response(status: 202, description: 'The statement is still being generated.', type: AiGenerationResource::class)]
    #[Response(status: 200, description: 'The statement text is ready.', type: AiGenerationResource::class)]
    #[Response(status: 502, description: 'The model failed to generate the statement.', type: AiGenerationResource::class)]
    #[Header(name: 'Retry-After', description: 'Seconds before the next poll.', status: 202, type: 'string', example: '2')]
    #[Header(name: 'Location', description: 'URL of the statement.', status: 202, type: 'string')]
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
