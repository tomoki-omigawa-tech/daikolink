<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">科目の管理</h2>
            <a href="{{ route('staff.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← アカウント管理</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <p class="text-sm text-gray-600">
                使わなくなった科目は「使う」のチェックを外すと非表示になります（講師の設定や時間割は残ります）。
            </p>

            @foreach ($stages as $stage)
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <h3 class="bg-gray-50 px-4 py-2 font-semibold text-gray-800">{{ $stage->label() }}</h3>
                    <div class="divide-y divide-gray-100">
                        @forelse ($subjectsByStage->get($stage->value, collect()) as $subject)
                            <form method="POST" action="{{ route('staff.subjects.update', $subject) }}" class="flex flex-wrap items-center gap-3 px-4 py-2 {{ $subject->is_active ? '' : 'bg-gray-50' }}">
                                @csrf
                                @method('PUT')
                                <x-text-input name="name" type="text" class="w-40 py-1 text-sm" :value="$subject->name" required maxlength="30" />
                                <label class="inline-flex items-center gap-1 text-sm">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600" @checked($subject->is_active)>
                                    使う
                                </label>
                                <span class="text-xs text-gray-500">担当できる講師 {{ $subject->users_count }} 人</span>
                                <x-secondary-button type="submit" class="ml-auto">更新</x-secondary-button>
                            </form>
                        @empty
                            <p class="px-4 py-3 text-sm text-gray-500">まだ科目がありません</p>
                        @endforelse
                    </div>
                </div>
            @endforeach

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800">科目を追加する</h3>
                <form method="POST" action="{{ route('staff.subjects.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <x-input-label for="stage" value="区分" />
                        <select id="stage" name="stage" class="mt-1 rounded-md border-gray-300 shadow-sm">
                            @foreach ($stages as $stage)
                                <option value="{{ $stage->value }}" @selected(old('stage') === $stage->value)>{{ $stage->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="new_name" value="科目名" />
                        <x-text-input id="new_name" name="name" type="text" class="mt-1 w-48" :value="old('name')" required maxlength="30" placeholder="例：地学基礎" />
                    </div>
                    <x-primary-button>追加</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
