<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'regex:/^\S+[\s　]+\S+/u'],
            'nickname' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:users,email'],
            'role' => ['required', Rule::in([User::ROLE_TEACHER, User::ROLE_STAFF])],
            'password' => ['required', 'confirmed', Password::defaults()],
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
            'role' => 'ロール',
            'password' => '初期パスワード',
        ];
    }
}
