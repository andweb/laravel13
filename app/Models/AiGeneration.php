<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiGenerationKind;
use App\Enums\AiGenerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'kind', 'status', 'for_date', 'idempotency_key', 'request', 'response', 'error'])]
class AiGeneration extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<AiGeneration>  $query
     * @return Builder<AiGeneration>
     */
    public function scopeHoroscopeFor(Builder $query, string $date): Builder
    {
        return $query
            ->where('kind', AiGenerationKind::Horoscope)
            ->whereDate('for_date', $date);
    }

    /**
     * @param  Builder<AiGeneration>  $query
     * @return Builder<AiGeneration>
     */
    public function scopeStatementForKey(Builder $query, int $userId, string $key): Builder
    {
        return $query
            ->where('kind', AiGenerationKind::Statement)
            ->where('user_id', $userId)
            ->where('idempotency_key', $key);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AiGenerationKind::class,
            'status' => AiGenerationStatus::class,
            'for_date' => 'date',
            'request' => 'array',
        ];
    }
}
