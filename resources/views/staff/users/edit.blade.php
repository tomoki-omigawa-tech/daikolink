@php use App\Enums\SubjectStage; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $user->name }} さんの編集</h2>
            <a href="{{ route('staff.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← アカウント管理</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('staff.users.update', $user) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="氏名（姓と名の間にスペース）" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required maxlength="50" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="nickname" value="ニックネーム（任意）" />
                        <x-text-input id="nickname" name="nickname" type="text" class="mt-1 block w-full" :value="old('nickname', $user->nickname)" maxlength="20" />
                        <x-input-error :messages="$errors->get('nickname')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" value="メールアドレス（変えると確認メールを送り直します）" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required maxlength="100" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div class="flex flex-wrap items-end gap-6">
                        <div>
                            <x-input-label for="role" value="ロール" />
                            <select id="role" name="role" class="mt-1 rounded-md border-gray-300 shadow-sm">
                                <option value="teacher" @selected(old('role', $user->role) === 'teacher')>講師</option>
                                <option value="staff" @selected(old('role', $user->role) === 'staff')>スタッフ</option>
                            </select>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600"
                                   @checked(old('is_active', $user->is_active))>
                            在籍中（外すとログインできなくなります）
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('role')" class="mt-2" />

                    @if ($user->isTeacher())
                        <div class="space-y-3">
                            <p class="font-medium text-sm text-gray-700">担当できる科目</p>
                            @php $checked = collect(old('subjects', $user->subjects->pluck('id')->all()))->map(fn ($id) => (int) $id)->all(); @endphp
                            @foreach (SubjectStage::cases() as $stage)
                                @if ($subjectsByStage->has($stage->value))
                                    <fieldset>
                                        <legend class="text-xs font-semibold text-gray-600">{{ $stage->label() }}</legend>
                                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-2">
                                            @foreach ($subjectsByStage[$stage->value] as $subject)
                                                <label class="inline-flex items-center gap-1 text-sm">
                                                    <input type="checkbox" name="subjects[]" value="{{ $subject->id }}" class="rounded border-gray-300 text-indigo-600"
                                                           @checked(in_array($subject->id, $checked, true))>
                                                    {{ $subject->name }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endif
                            @endforeach
                            <x-input-error :messages="$errors->get('subjects.*')" class="mt-2" />
                        </div>
                    @endif

                    <div class="flex items-center gap-4">
                        <x-primary-button>保存する</x-primary-button>
                        <a href="{{ route('staff.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
