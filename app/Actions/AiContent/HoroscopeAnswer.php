<?php

declare(strict_types=1);

namespace App\Actions\AiContent;

use App\Models\AiGeneration;

final class HoroscopeAnswer
{
    private function __construct(
        public ?AiGeneration $generation,
        public ?int $id,
        public ?string $text,
    ) {}

    public static function model(AiGeneration $generation): self
    {
        return new self($generation, null, null);
    }

    public static function cached(int $id, string $text): self
    {
        return new self(null, $id, $text);
    }

    public function fromCache(): bool
    {
        return $this->generation === null;
    }
}
