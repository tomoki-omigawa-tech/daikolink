<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // 苗字を表示に使うため、姓と名の間にスペースが必要
            'name' => ['required', 'string', 'max:50', 'regex:/^\S+[\s　]+\S+/u'],
            'nickname' => ['nullable', 'string', 'max:20'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:100',
                Rule::unique(User::class)->ignore($this->user()?->id),
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => '氏名は姓と名の間にスペースを入れてください（例：山田 太郎）。',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'nickname' => 'ニックネーム',
            'email' => 'メールアドレス',
            'avatar' => 'プロフィール画像',
        ];
    }
}
