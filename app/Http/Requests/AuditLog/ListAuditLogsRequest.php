<?php

namespace App\Http\Requests\AuditLog;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAuditLogsRequest extends FormRequest
{
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
            'user_id' => ['nullable', 'integer'],
            'event' => ['nullable', Rule::in(AuditLogger::EVENTS)],
            // An id means nothing without its type ("order 5" vs "user 5").
            'auditable_type' => ['nullable', 'required_with:auditable_id', Rule::in(array_keys(AuditLog::SUBJECT_TYPES))],
            'auditable_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
