<?php

namespace App\Http\Requests\Staff;

use App\Enums\TimetablePattern;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftCellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $lesson = fn (string $key) => [
            $key => ['nullable', 'array'],
            "{$key}.grade" => ['nullable', 'string'],
            "{$key}.subject_id" => ['nullable', 'integer'],
        ];

        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'pattern' => ['required', Rule::enum(TimetablePattern::class)],
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', User::ROLE_TEACHER)->where('is_active', true),
            ],
            'period' => ['required', 'integer', 'between:1,7'],
            'seat' => ['required', 'integer', 'in:1,2'],
            'mode' => ['required', 'in:empty,secondary,elementary,group'],
            ...$lesson('secondary'),
            ...$lesson('first'),
            ...$lesson('second'),
        ];
    }
}
