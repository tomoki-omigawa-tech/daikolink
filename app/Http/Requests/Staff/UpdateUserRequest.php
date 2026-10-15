<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['required', Rule::in([User::ROLE_TEACHER, User::ROLE_STAFF])],
            'is_active' => ['nullable', 'boolean'],
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer', Rule::exists('subjects', 'id')->where('is_active', true)],
        ];
    }

    /**
     * 自分自身のロール・在籍は変えられない（スタッフがいなくなる事故を防ぐ）
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->route('user');
                if (! $target instanceof User || $target->id !== $this->user()?->id) {
                    return;
                }
                if ($this->input('role') !== User::ROLE_STAFF || ! $this->boolean('is_active')) {
                    $validator->errors()->add('role', '自分自身のロール・在籍は変更できません。');
                }
            },
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
            'subjects' => '担当できる科目',
        ];
    }
}
