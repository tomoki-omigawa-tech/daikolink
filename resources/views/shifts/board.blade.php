@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">シフト表</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('shifts.board') }}" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap items-end gap-3">
                <a href="{{ route('shifts.board', ['date' => $date->copy()->subDay()->toDateString()]) }}"
                   class="px-3 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">← 前の日</a>
                <div>
                    <x-input-label for="date" value="日付" />
                    <x-text-input id="date" name="date" type="date" class="mt-1" :value="$date->toDateString()" onchange="this.form.submit()" />
                </div>
                <a href="{{ route('shifts.board', ['date' => $date->copy()->addDay()->toDateString()]) }}"
                   class="px-3 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">次の日 →</a>
                <p class="text-sm text-gray-600">
                    <span class="font-semibold text-gray-900">{{ DateLabel::date($date) }}</span>
                    @if ($pattern && ! $closedDay) ／ {{ $pattern->label() }}の時間割 @endif
                </p>
            </form>

            @if ($closedDay)
                <div class="rounded-md bg-gray-100 p-6 text-sm text-gray-700">
                    この日は休みです{{ $closedDay->note ? '（'.$closedDay->note.'）' : '' }}。
                </div>
            @elseif ($pattern === null)
                <div class="rounded-md bg-gray-100 p-6 text-sm text-gray-700">この日は休みです。</div>
            @else
                @if ($teachers->isEmpty())
                    <p class="text-sm text-gray-600">この日のシフトはまだありません。</p>
                @else
                    <p class="text-sm text-gray-600">講師名をクリックするとプロフィール（担当できる科目）が見られます。オレンジの枠は代講を募集中の授業です。</p>
                    @include('partials.timetable-grid', [
                        'editable' => false,
                        'showRequests' => true,
                        'highlightUserId' => auth()->id(),
                        'profileTeachers' => $allTeachers,
                    ])
                @endif

                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <h3 class="bg-gray-50 px-4 py-2 font-semibold text-gray-800">この日の空き状況</h3>
                    <div class="divide-y divide-gray-100">
                        @foreach ($availability as $row)
                            <div class="flex flex-wrap gap-4 px-4 py-3 text-sm">
                                <div class="w-24">
                                    <p class="font-semibold text-gray-900">{{ $row['label'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $row['time'] }}</p>
                                </div>
                                <div class="flex-1 space-y-2">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="w-36 text-xs text-gray-500">空いている（{{ $row['free']->count() }}人）</span>
                                        @forelse ($row['free'] as $teacher)
                                            <button type="button" x-data @click="$dispatch('show-profile', {{ $teacher->id }})"
                                                    class="rounded bg-green-100 px-2 py-0.5 text-green-800 hover:underline">{{ $teacher->displayName() }}</button>
                                        @empty
                                            <span class="text-gray-400">—</span>
                                        @endforelse
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="w-36 text-xs text-gray-500">席が片方空いている（{{ $row['half']->count() }}人）</span>
                                        @forelse ($row['half'] as $teacher)
                                            <button type="button" x-data @click="$dispatch('show-profile', {{ $teacher->id }})"
                                                    class="rounded bg-sky-100 px-2 py-0.5 text-sky-800 hover:underline">{{ $teacher->displayName() }}</button>
                                        @empty
                                            <span class="text-gray-400">—</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($teachers->isEmpty())
                    @include('partials.teacher-profile-modal', ['profiles' => \App\Support\TeacherProfiles::make($allTeachers)])
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
