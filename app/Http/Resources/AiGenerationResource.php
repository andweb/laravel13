<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AiGenerationStatus;
use App\Models\AiGeneration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiGenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $generation = $this->resource;

        if (! $generation instanceof AiGeneration) {
            throw new \RuntimeException('AI generation resource expects an AiGeneration model.');
        }

        $status = $generation->status;

        return [
            'id' => $generation->id,
            'status' => $status->value,
            'text' => $status === AiGenerationStatus::Done ? $generation->response : null,
        ];
    }
}
