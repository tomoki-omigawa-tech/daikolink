<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">アカウント管理</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('staff.subjects.index') }}" class="text-sm text-gray-600 hover:text-gray-900">科目の管理</a>
                <a href="{{ route('staff.users.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md text-xs font-semibold text-white hover:bg-gray-700">
                    ＋ アカウント発行
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">氏名</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">メールアドレス</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">ロール</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">状態</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @if ($user->avatarUrl())
                                            <img src="{{ $user->avatarUrl() }}" alt="" class="h-8 w-8 rounded-full object-cover">
                                        @endif
                                        <div>
                                            <div>{{ $user->name }}</div>
                                            @if ($user->nickname)
                                                <div class="text-xs text-gray-500">{{ $user->nickname }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                                <td class="px-4 py-3">{{ $user->isStaff() ? 'スタッフ' : '講師' }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->is_active)
                                        <span class="rounded bg-green-100 px-2 py-1 text-xs text-green-800">在籍</span>
                                    @else
                                        <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-500">退職</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('staff.users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-800">編集</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
