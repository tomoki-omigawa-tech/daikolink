<?php

namespace App\Http\Requests\Staff;

use App\Models\Term;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'fiscal_year' => ['required', 'integer', 'between:2020,2100'],
            'term_no' => [
                'required',
                'integer',
                'in:1,2,3',
                Rule::unique('terms')->where('fiscal_year', $this->input('fiscal_year')),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
        ];
    }

    /**
     * 期間がほかの学期と重ならないか
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $overlaps = Term::where('start_date', '<=', $this->input('end_date'))
                    ->where('end_date', '>=', $this->input('start_date'))
                    ->exists();

                if ($overlaps) {
                    $validator->errors()->add('start_date', 'ほかの学期と期間が重なっています。');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'fiscal_year' => '年度',
            'term_no' => '学期',
            'start_date' => '開始日',
            'end_date' => '終了日',
        ];
    }
}
