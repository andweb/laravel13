<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

enum AiGenerationKind: string
{
    case Statement = 'statement';
    case Horoscope = 'horoscope';

    public function systemPrompt(): string
    {
        return $this->read($this->value.'/system.md');
    }

    /**
     * @param  array<string, mixed>  $request
     */
    public function userPrompt(array $request): string
    {
        return match ($this) {
            self::Statement => $this->problem($request),
            self::Horoscope => 'гороскоп на '.$this->russianDate($this->date($request)).'.',
        };
    }

    public function compose(string $middle): string
    {
        $middle = trim($middle);

        if ($middle === '') {
            throw new InvalidArgumentException('Completion is empty.');
        }

        return $middle;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function problem(array $request): string
    {
        $problem = $request['problem'] ?? null;

        if (! is_string($problem) || $problem === '') {
            throw new InvalidArgumentException('Statement request has no problem.');
        }

        return $problem;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function date(array $request): string
    {
        $date = $request['date'] ?? null;

        if (! is_string($date) || $date === '') {
            throw new InvalidArgumentException('Horoscope request has no date.');
        }

        return $date;
    }

    private function russianDate(string $date): string
    {
        $parsed = CarbonImmutable::parse($date);
        $months = [
            1 => 'января',
            2 => 'февраля',
            3 => 'марта',
            4 => 'апреля',
            5 => 'мая',
            6 => 'июня',
            7 => 'июля',
            8 => 'августа',
            9 => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря',
        ];
        $weekdays = [
            0 => 'воскресенье',
            1 => 'понедельник',
            2 => 'вторник',
            3 => 'среда',
            4 => 'четверг',
            5 => 'пятница',
            6 => 'суббота',
        ];

        return $parsed->day.' '.$months[$parsed->month].' '.$parsed->year.' ('.$weekdays[$parsed->dayOfWeek].')';
    }

    private function read(string $path): string
    {
        $fullPath = resource_path('prompts/'.$path);
        $contents = file_get_contents($fullPath);

        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException("Prompt [{$path}] is missing.");
        }

        return trim($contents);
    }
}
