<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportRequest extends FormRequest
{
    private const int MAX_DAYS = 92;

    private const int DEFAULT_DAYS = 7;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->isEmpty()) {
                    return;
                }

                if ($this->startDate()->diffInDays($this->endDate()) >= self::MAX_DAYS) {
                    $validator->errors()->add('end_date', 'The report can cover at most '.self::MAX_DAYS.' days.');
                }
            },
        ];
    }

    public function startDate(): CarbonImmutable
    {
        return $this->filled('start_date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $this->input('start_date'), 'UTC')->startOfDay()
            : $this->endDate()->subDays(self::DEFAULT_DAYS - 1)->startOfDay();
    }

    public function endDate(): CarbonImmutable
    {
        return $this->filled('end_date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $this->input('end_date'), 'UTC')->endOfDay()
            : CarbonImmutable::now('UTC')->endOfDay();
    }
}
