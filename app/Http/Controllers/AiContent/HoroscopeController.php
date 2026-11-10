<?php

declare(strict_types=1);

namespace App\Http\Controllers\AiContent;

use App\Actions\AiContent\RequestHoroscope;
use App\Enums\AiGenerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiContent\ShowHoroscopeRequest;
use App\Http\Resources\AiGenerationResource;
use App\Models\AiGeneration;
use Illuminate\Http\JsonResponse;

class HoroscopeController extends Controller
{
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
