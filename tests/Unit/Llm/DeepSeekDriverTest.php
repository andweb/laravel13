<?php

declare(strict_types=1);

namespace Tests\Unit\Llm;

use App\Services\Llm\LlmManager;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DeepSeekDriverTest extends TestCase
{
    public function test_it_sends_a_chat_completion_request(): void
    {
        config([
            'ai-content.driver' => 'deepseek',
            'ai-content.drivers.deepseek' => $this->deepSeekConfig(),
        ]);

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Середина заявления.']],
                ],
            ]),
        ]);

        $text = $this->app->make(LlmManager::class)->complete('Ты пишешь заявление.', 'Прорвало трубу.');

        $this->assertSame('Середина заявления.', $text);

        Http::assertSent(function (Request $request): bool {
            /** @var array<string, mixed> $body */
            $body = $request->data();

            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && ($body['model'] ?? null) === 'deepseek-flash'
                && ($body['stream'] ?? null) === false
                && ($body['max_tokens'] ?? null) === 2000
                && ($body['thinking'] ?? null) === ['type' => 'disabled']
                && ($body['messages'] ?? null) === [
                    ['role' => 'system', 'content' => 'Ты пишешь заявление.'],
                    ['role' => 'user', 'content' => 'Прорвало трубу.'],
                ];
        });
    }

    public function test_it_throws_when_deepseek_returns_a_server_error(): void
    {
        config([
            'ai-content.driver' => 'deepseek',
            'ai-content.drivers.deepseek' => $this->deepSeekConfig(),
        ]);

        Http::fake([
            'https://api.deepseek.com/*' => Http::response(['error' => 'unavailable'], 500),
        ]);

        $this->expectException(RequestException::class);

        $this->app->make(LlmManager::class)->complete('system', 'user');
    }

    public function test_it_throws_when_the_completion_is_empty(): void
    {
        config([
            'ai-content.driver' => 'deepseek',
            'ai-content.drivers.deepseek' => $this->deepSeekConfig(),
        ]);

        Http::fake([
            'https://api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '']],
                ],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DeepSeek returned an empty completion.');

        $this->app->make(LlmManager::class)->complete('system', 'user');
    }

    /**
     * @return array{key: string, url: string, model: string, timeout: int, max_tokens: int}
     */
    private function deepSeekConfig(): array
    {
        return [
            'key' => 'test-key',
            'url' => 'https://api.deepseek.com',
            'model' => 'deepseek-flash',
            'timeout' => 60,
            'max_tokens' => 2000,
        ];
    }
}
