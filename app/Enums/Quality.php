<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Quality: string
{
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
