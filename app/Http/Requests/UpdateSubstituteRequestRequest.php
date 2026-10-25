<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubstituteRequestRequest extends FormRequest
{
    /**
     * 「自分の依頼か」はコントローラーで Policy を使って確認する
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
            'handover_note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => '理由',
            'handover_note' => '引き継ぎメモ',
        ];
    }
}
