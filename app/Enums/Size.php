<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Size: string
{
    use HasOptions;

    case Square = '1024x1024';
    case Landscape = '1536x1024';
    case Portrait = '1024x1536';
}
