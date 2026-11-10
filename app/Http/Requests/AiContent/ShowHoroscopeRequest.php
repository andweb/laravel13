<?php

declare(strict_types=1);

namespace App\Http\Requests\AiContent;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ShowHoroscopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('date') === '') {
            $this->merge(['date' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['bail', 'nullable', 'date_format:Y-m-d', $this->withinRange(...)],
        ];
    }

    public function forDate(): string
    {
        $date = $this->validated('date');

        if (is_string($date) && $date !== '') {
            return $date;
        }

        return CarbonImmutable::now($this->timezone())->toDateString();
    }

    private function withinRange(string $attribute, mixed $value, Closure $fail, mixed $validator = null): void
    {
        if (! is_string($value)) {
            return;
        }

        $timezone = $this->timezone();

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        } catch (InvalidFormatException) {
            $fail('The date does not match the format Y-m-d.');

            return;
        }
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $range = (int) config('ai-content.horoscope.days_range');

        if (! $day instanceof CarbonImmutable || $day->startOfDay()->lt($today->subDays($range)) || $day->startOfDay()->gt($today->addDays($range))) {
            $fail('The date is outside the allowed range.');
        }
    }

    private function timezone(): string
    {
        $timezone = config('ai-content.timezone');

        if (! is_string($timezone) || $timezone === '') {
            throw new \RuntimeException('Horoscope timezone is not configured.');
        }

        return $timezone;
    }
}
