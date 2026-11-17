<?php

namespace App\Exceptions;

use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $available)
    {
        parent::__construct(sprintf(
            'Not enough credits: this image can cost up to $%s and you have $%s available.',
            Money::toDecimal($required),
            Money::toDecimal(max($available, 0)),
        ));
    }

    public function render(): RedirectResponse
    {
        return back()->withErrors(['credits' => $this->getMessage()]);
    }
}
