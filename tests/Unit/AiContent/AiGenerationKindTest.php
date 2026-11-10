<?php

declare(strict_types=1);

namespace Tests\Unit\AiContent;

use App\Enums\AiGenerationKind;
use InvalidArgumentException;
use Tests\TestCase;

class AiGenerationKindTest extends TestCase
{
    public function test_system_prompts_are_read_from_markdown(): void
    {
        $statement = AiGenerationKind::Statement->systemPrompt();
        $horoscope = AiGenerationKind::Horoscope->systemPrompt();

        $this->assertSame($this->prompt('statement/system.md'), $statement);
        $this->assertStringContainsString('только середину', $statement);
        $this->assertSame($this->prompt('horoscope/system.md'), $horoscope);
        $this->assertStringContainsString('Овен', $horoscope);
        $this->assertStringContainsString('Рыбы', $horoscope);
    }

    public function test_statement_is_assembled_from_header_middle_and_footer(): void
    {
        $text = AiGenerationKind::Statement->compose('Середина.', 'Анна', '10.11.2026');

        $this->assertSame(<<<'TEXT'
В управляющую компанию

от Анна
10.11.2026

Середина.

Прошу рассмотреть обращение и ответить в установленный срок.

Анна
10.11.2026
TEXT, $text);
    }

    public function test_horoscope_keeps_the_model_text(): void
    {
        $text = AiGenerationKind::Horoscope->compose("Овен. День спокойный.\n\nТелец. День деловой.");

        $this->assertSame("Овен. День спокойный.\n\nТелец. День деловой.", $text);
    }

    public function test_user_prompts_come_from_the_request(): void
    {
        $problem = AiGenerationKind::Statement->userPrompt([
            'problem' => 'Прорвало трубу на кухне.',
        ]);
        $horoscope = AiGenerationKind::Horoscope->userPrompt([
            'date' => '2026-11-10',
        ]);

        $this->assertSame('Прорвало трубу на кухне.', $problem);
        $this->assertSame('гороскоп на 10 ноября 2026 (вторник).', $horoscope);
    }

    public function test_empty_completion_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Completion is empty.');

        AiGenerationKind::Statement->compose('   ');
    }

    private function prompt(string $path): string
    {
        $contents = file_get_contents(resource_path('prompts/'.$path));

        $this->assertIsString($contents);

        return trim($contents);
    }
}
