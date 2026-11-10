<?php

declare(strict_types=1);

namespace App\Services\Llm;

use Illuminate\Support\Manager;
use InvalidArgumentException;

class LlmManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $driver = $this->config->get('ai-content.driver');

        if (! is_string($driver) || $driver === '') {
            throw new InvalidArgumentException('AI content driver is not configured.');
        }

        return $driver;
    }

    public function complete(string $system, string $user): string
    {
        $driver = $this->driver();

        if (! $driver instanceof LlmDriver) {
            throw new InvalidArgumentException('AI content driver must implement '.LlmDriver::class.'.');
        }

        return $driver->complete($system, $user);
    }

    protected function createDeepseekDriver(): DeepSeekDriver
    {
        /** @var mixed $config */
        $config = $this->config->get('ai-content.drivers.deepseek');

        if (! is_array($config)) {
            throw new InvalidArgumentException('DeepSeek driver is not configured.');
        }

        return new DeepSeekDriver(
            key: $this->stringConfig($config, 'key'),
            url: $this->stringConfig($config, 'url'),
            model: $this->stringConfig($config, 'model'),
            timeout: $this->intConfig($config, 'timeout'),
            maxTokens: $this->intConfig($config, 'max_tokens'),
        );
    }

    protected function createFakeDriver(): FakeDriver
    {
        /** @var mixed $text */
        $text = $this->config->get('ai-content.drivers.fake.text');

        if (! is_string($text)) {
            throw new InvalidArgumentException('Fake driver text is not configured.');
        }

        return new FakeDriver($text);
    }

    /**
     * @param  array<mixed>  $config
     */
    private function stringConfig(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("DeepSeek driver [{$key}] is not configured.");
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $config
     */
    private function intConfig(array $config, string $key): int
    {
        $value = $config[$key] ?? null;

        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("DeepSeek driver [{$key}] is not configured.");
        }

        return $value;
    }
}
