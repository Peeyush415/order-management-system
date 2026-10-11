<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * ?from=2026-09-01&to=2026-09-30&period=day|week|month. Defaults to the last 30 days.
 */
class DashboardRequest extends FormRequest
{
    /** Limits how much one request can aggregate. */
    private const MAX_DAYS = 366;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'period' => ['nullable', Rule::in(['day', 'week', 'month'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty() && $this->from()->diffInDays($this->to()) >= self::MAX_DAYS) {
                    $validator->errors()->add('to', 'The date range can be at most '.self::MAX_DAYS.' days.');
                }
            },
        ];
    }

    public function to(): CarbonImmutable
    {
        return $this->filled('to') ? CarbonImmutable::parse($this->input('to')) : CarbonImmutable::today();
    }

    public function from(): CarbonImmutable
    {
        return $this->filled('from') ? CarbonImmutable::parse($this->input('from')) : $this->to()->subDays(29);
    }

    public function period(): string
    {
        return $this->input('period', 'day');
    }
}
