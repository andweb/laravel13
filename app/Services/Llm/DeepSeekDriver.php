<?php

declare(strict_types=1);

namespace App\Services\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekDriver implements LlmDriver
{
    public function __construct(
        private readonly string $key,
        private readonly string $url,
        private readonly string $model,
        private readonly int $timeout,
        private readonly int $maxTokens,
    ) {}

    public function complete(string $system, string $user): string
    {
        $response = Http::withToken($this->key)
            ->timeout($this->timeout)
            ->retry(2, 100, fn ($exception): bool => $exception instanceof ConnectionException)
            ->acceptJson()
            ->post($this->endpoint(), [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'thinking' => ['type' => 'disabled'],
                'max_tokens' => $this->maxTokens,
                'stream' => false,
            ])
            ->throw();

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('DeepSeek returned an empty completion.');
        }

        return $content;
    }

    private function endpoint(): string
    {
        return rtrim($this->url, '/').'/chat/completions';
    }
}
