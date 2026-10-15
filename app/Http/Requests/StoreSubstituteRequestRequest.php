<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubstituteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'shift_ids' => ['required', 'array', 'min:1'],
            'shift_ids.*' => ['integer', 'distinct'],
            'reason' => ['nullable', 'string', 'max:255'],
            'handover_note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'shift_ids.required' => '代講をお願いする授業を選んでください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'shift_ids' => '授業',
            'reason' => '理由',
            'handover_note' => '引き継ぎメモ',
        ];
    }
}
