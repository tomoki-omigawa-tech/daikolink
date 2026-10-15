<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">アカウント発行</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('staff.users.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="name" value="氏名（姓と名の間にスペース）" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus maxlength="50" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="nickname" value="ニックネーム（任意・表で大きく表示されます）" />
                        <x-text-input id="nickname" name="nickname" type="text" class="mt-1 block w-full" :value="old('nickname')" maxlength="20" />
                        <x-input-error :messages="$errors->get('nickname')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" value="メールアドレス（ログインIDと通知先になります）" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required maxlength="100" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="role" value="ロール" />
                        <select id="role" name="role" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="teacher" @selected(old('role', 'teacher') === 'teacher')>講師</option>
                            <option value="staff" @selected(old('role') === 'staff')>スタッフ</option>
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password" value="初期パスワード（8文字以上）" />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" value="初期パスワード（確認）" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>発行する</x-primary-button>
                        <a href="{{ route('staff.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
