<?php

declare(strict_types=1);

namespace App\Http\Requests\AiContent;

use Illuminate\Foundation\Http\FormRequest;

class StoreStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'problem' => ['required', 'string', 'min:20', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function idempotencyKey(): string
    {
        $key = $this->validated('idempotency_key');

        if (! is_string($key)) {
            throw new \RuntimeException('Idempotency key is missing.');
        }

        return $key;
    }
}
