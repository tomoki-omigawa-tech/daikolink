@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ダッシュボード</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">今日（{{ DateLabel::date(today()) }}）の授業</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">
                        {{ $isClosedToday ? '休み' : $todayShifts.' 件' }}
                    </p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">まだ決まっていない代講依頼</p>
                    <p class="mt-1 text-3xl font-bold {{ $activeRequests > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ $activeRequests }} 件</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="{{ route('staff.shifts.index') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:ring-2 hover:ring-indigo-400">
                    <p class="font-semibold text-gray-900">シフト管理</p>
                    <p class="mt-1 text-sm text-gray-600">日ごとのシフトの変更・休みの日</p>
                </a>
                <a href="{{ route('staff.terms.index') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:ring-2 hover:ring-indigo-400">
                    <p class="font-semibold text-gray-900">時間割</p>
                    <p class="mt-1 text-sm text-gray-600">学期の時間割とシフトへの反映</p>
                </a>
                <a href="{{ route('staff.users.index') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:ring-2 hover:ring-indigo-400">
                    <p class="font-semibold text-gray-900">アカウント管理</p>
                    <p class="mt-1 text-sm text-gray-600">講師のアカウント・担当科目・科目</p>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
