<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AiGenerationStatus;
use App\Models\AiGeneration;
use App\Services\Llm\LlmManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateAiContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 75;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    public function __construct(public AiGeneration $generation) {}

    public function handle(LlmManager $llm): void
    {
        $generation = $this->generation->fresh();

        if (! $generation instanceof AiGeneration || $generation->status !== AiGenerationStatus::Pending) {
            return;
        }

        $kind = $generation->kind;
        $middle = $llm->complete($kind->systemPrompt(), $kind->userPrompt($generation->request));
        $date = $generation->created_at
            ->timezone((string) config('ai-content.timezone'))
            ->format('d.m.Y');

        AiGeneration::query()
            ->whereKey($generation->id)
            ->where('status', AiGenerationStatus::Pending->value)
            ->update([
                'response' => $kind->compose($middle, $generation->user?->name ?? '', $date),
                'status' => AiGenerationStatus::Done->value,
                'error' => null,
                'updated_at' => now(),
            ]);
    }

    public function failed(?Throwable $exception): void
    {
        $generation = $this->generation->fresh();

        if (! $generation instanceof AiGeneration || $generation->status === AiGenerationStatus::Done) {
            return;
        }

        $generation->update([
            'status' => AiGenerationStatus::Failed,
            'error' => $exception instanceof Throwable ? class_basename($exception) : 'Generation failed.',
        ]);
    }
}
