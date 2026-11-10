<?php

declare(strict_types=1);

namespace App\Http\Controllers\AiContent;

use App\Actions\AiContent\RequestHoroscope;
use App\Enums\AiGenerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiContent\ShowHoroscopeRequest;
use App\Http\Resources\AiGenerationResource;
use App\Models\AiGeneration;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class HoroscopeController extends Controller
{
    #[Response(status: 202, description: 'The horoscope is still being generated.', type: AiGenerationResource::class)]
    #[Response(status: 200, description: 'The horoscope text is ready.', type: AiGenerationResource::class)]
    #[Header(name: 'Retry-After', description: 'Seconds before the next poll.', status: 202, type: 'string', example: '2')]
    public function __invoke(ShowHoroscopeRequest $request, RequestHoroscope $action): JsonResponse
    {
        $answer = $action->handle($request->forDate());

        if ($answer->fromCache()) {
            return response()->json([
                'data' => [
                    'id' => $answer->id,
                    'status' => AiGenerationStatus::Done->value,
                    'text' => $answer->text,
                ],
            ]);
        }

        $generation = $answer->generation;

        if (! $generation instanceof AiGeneration) {
            throw new \RuntimeException('Horoscope answer is empty.');
        }

        $response = (new AiGenerationResource($generation))->response();

        return match ($generation->status) {
            AiGenerationStatus::Pending => $response->setStatusCode(202)->header('Retry-After', '2'),
            AiGenerationStatus::Done => $response->setStatusCode(200),
            AiGenerationStatus::Failed => $response->setStatusCode(502),
        };
    }
}
