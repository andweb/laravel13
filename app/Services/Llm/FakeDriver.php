<?php

declare(strict_types=1);

namespace App\Services\Llm;

class FakeDriver implements LlmDriver
{
    public function __construct(private readonly string $text) {}

    public function complete(string $system, string $user): string
    {
        return $this->text;
    }
}
