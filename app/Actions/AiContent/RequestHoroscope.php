<?php

declare(strict_types=1);

namespace App\Actions\AiContent;

use App\Enums\AiGenerationKind;
use App\Enums\AiGenerationStatus;
use App\Jobs\GenerateAiContent;
use App\Models\AiGeneration;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RequestHoroscope
{
    public function handle(string $date): HoroscopeAnswer
    {
        $cached = Cache::get($this->cacheKey($date));

        if (is_array($cached) && is_int($cached['id'] ?? null) && is_string($cached['text'] ?? null) && $cached['text'] !== '') {
            return HoroscopeAnswer::cached($cached['id'], $cached['text']);
        }

        $generation = DB::transaction(function () use ($date): AiGeneration {
            $existing = AiGeneration::query()->horoscopeFor($date)->first();

            if ($existing instanceof AiGeneration) {
                return $this->continueExisting($existing);
            }

            try {
                $created = AiGeneration::query()->create([
                    'kind' => AiGenerationKind::Horoscope,
                    'for_date' => $date,
                    'status' => AiGenerationStatus::Pending,
                    'request' => ['date' => $date],
                ]);
            } catch (UniqueConstraintViolationException) {
                $existing = AiGeneration::query()->horoscopeFor($date)->first();

                if (! $existing instanceof AiGeneration) {
                    throw new RuntimeException('Horoscope row was not found after a unique constraint violation.');
                }

                return $this->continueExisting($existing);
            }

            GenerateAiContent::dispatch($created)->afterCommit();

            return $created;
        });

        if ($generation->status === AiGenerationStatus::Done && is_string($generation->response) && $generation->response !== '') {
            Cache::put($this->cacheKey($date), [
                'id' => (int) $generation->id,
                'text' => $generation->response,
            ], CarbonImmutable::now($this->timezone())->endOfDay());
        }

        return HoroscopeAnswer::model($generation);
    }

    private function continueExisting(AiGeneration $generation): AiGeneration
    {
        if ($generation->status !== AiGenerationStatus::Failed) {
            return $generation;
        }

        $restarted = AiGeneration::query()
            ->whereKey($generation->id)
            ->where('status', AiGenerationStatus::Failed->value)
            ->update([
                'status' => AiGenerationStatus::Pending->value,
                'error' => null,
                'updated_at' => now(),
            ]);

        if ($restarted === 1) {
            GenerateAiContent::dispatch($generation)->afterCommit();
        }

        return $generation->refresh();
    }

    private function cacheKey(string $date): string
    {
        return 'ai-content:horoscope:'.$date;
    }

    private function timezone(): string
    {
        $timezone = config('ai-content.timezone');

        if (! is_string($timezone) || $timezone === '') {
            throw new RuntimeException('Horoscope timezone is not configured.');
        }

        return $timezone;
    }
}
