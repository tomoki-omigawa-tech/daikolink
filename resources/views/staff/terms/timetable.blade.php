@php
    use App\Support\DateLabel;

    $cellClass = [
        'secondary' => 'bg-sky-50',
        'first' => 'bg-amber-50',
        'second' => 'bg-amber-50 border-t-dashed',
        'group' => 'bg-violet-50',
        'empty' => 'bg-white',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $term->label() }}の時間割</h2>
            <a href="{{ route('staff.terms.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← 学期の一覧</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <p class="text-sm text-gray-600">
                {{ DateLabel::date($term->start_date) }}〜{{ DateLabel::date($term->end_date) }}
            </p>

            <nav class="flex gap-1 overflow-x-auto" aria-label="曜日">
                @foreach (range(1, 6) as $day)
                    <a href="{{ route('staff.terms.timetable', [$term, 'weekday' => $day]) }}"
                       class="px-4 py-2 rounded-md text-sm whitespace-nowrap {{ $day === $weekday ? 'bg-gray-800 text-white font-semibold' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
                        {{ DateLabel::weekday($day) }}曜
                    </a>
                @endforeach
            </nav>

            <div class="flex flex-wrap gap-4 text-xs text-gray-600">
                <span><span class="inline-block w-3 h-3 align-middle bg-sky-50 border border-gray-300"></span> 中高生</span>
                <span><span class="inline-block w-3 h-3 align-middle bg-amber-50 border border-gray-300"></span> 小学生（上＝1/2、下＝2/2）</span>
                <span><span class="inline-block w-3 h-3 align-middle bg-violet-50 border border-gray-300"></span> 小学生集団</span>
                <span>⚠ 担当できる科目に入っていない</span>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="border-collapse text-xs">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-20 bg-gray-50 border border-gray-200 px-2 py-2 text-left min-w-[5rem]">限</th>
                            @foreach ($teachers as $teacher)
                                <th colspan="2" class="bg-gray-50 border border-gray-200 px-2 py-1 min-w-[12rem]">
                                    <div class="text-sm font-bold text-gray-900">{{ $teacher->displayName() }}</div>
                                    @if ($teacher->nickname)
                                        <div class="text-[11px] font-normal text-gray-500">{{ $teacher->surnameLabel() }}</div>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grid as $period)
                            @foreach ($period['rows'] as $rowIndex => $cells)
                                <tr>
                                    @if ($rowIndex === 0)
                                        <th rowspan="{{ count($period['rows']) }}" class="sticky left-0 z-10 bg-gray-50 border border-gray-200 px-2 py-2 text-left align-top">
                                            <div class="text-sm font-bold text-gray-900">{{ $period['label'] }}</div>
                                            <div class="text-[11px] font-normal text-gray-500 whitespace-nowrap">{{ $period['time'] }}</div>
                                        </th>
                                    @endif
                                    @foreach ($cells as $cell)
                                        <td rowspan="{{ $cell['rowspan'] }}" colspan="{{ $cell['colspan'] }}"
                                            class="border border-gray-200 px-1 {{ count($period['rows']) === 2 ? 'h-12' : 'h-24' }} w-24 min-w-[6rem] text-center align-middle whitespace-nowrap {{ $cell['lesson'] ? $cellClass[$cell['type']] : 'bg-white' }}">
                                            {{ $cell['label'] }}
                                            @if ($cell['warn'])
                                                <span class="text-amber-600" title="担当できる科目に入っていません">⚠</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
