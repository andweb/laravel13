<?php

declare(strict_types=1);

namespace App\Enums;

enum AiGenerationStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
}
