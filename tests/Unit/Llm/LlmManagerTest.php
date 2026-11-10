<?php

declare(strict_types=1);

namespace Tests\Unit\Llm;

use App\Services\Llm\DeepSeekDriver;
use App\Services\Llm\FakeDriver;
use App\Services\Llm\LlmManager;
use InvalidArgumentException;
use Tests\TestCase;

class LlmManagerTest extends TestCase
{
    public function test_it_resolves_the_driver_from_config(): void
    {
        config(['ai-content.driver' => 'fake']);

        $driver = $this->app->make(LlmManager::class)->driver();

        $this->assertInstanceOf(FakeDriver::class, $driver);
    }

    public function test_it_resolves_the_deepseek_driver(): void
    {
        config([
            'ai-content.driver' => 'deepseek',
            'ai-content.drivers.deepseek' => $this->deepSeekConfig(),
        ]);

        $driver = $this->app->make(LlmManager::class)->driver();

        $this->assertInstanceOf(DeepSeekDriver::class, $driver);
    }

    public function test_it_rejects_an_unknown_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [missing] not supported.');

        $this->app->make(LlmManager::class)->driver('missing');
    }

    public function test_it_returns_the_fake_driver_text(): void
    {
        config([
            'ai-content.driver' => 'fake',
            'ai-content.drivers.fake.text' => 'Готовый текст.',
        ]);

        $text = $this->app->make(LlmManager::class)->complete('system', 'user');

        $this->assertSame('Готовый текст.', $text);
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
