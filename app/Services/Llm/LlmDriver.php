<?php

declare(strict_types=1);

namespace App\Services\Llm;

interface LlmDriver
{
    public function complete(string $system, string $user): string;
}
