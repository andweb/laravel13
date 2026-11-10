<?php

declare(strict_types=1);

namespace Tests\Unit\Llm;

use App\Services\Llm\FakeDriver;
use Tests\TestCase;

class FakeDriverTest extends TestCase
{
    public function test_it_returns_the_configured_text(): void
    {
        $text = (new FakeDriver('Фиксированный ответ.'))->complete('system', 'user');

        $this->assertSame('Фиксированный ответ.', $text);
    }
}
