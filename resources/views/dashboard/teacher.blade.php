@php
    use App\Support\DateLabel;

    $cellClass = [
        'secondary' => 'bg-sky-50',
        'first' => 'bg-amber-50',
        'second' => 'bg-amber-50',
        'group' => 'bg-violet-50',
        'empty' => 'bg-white',
        'none' => 'bg-gray-100',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">マイシフト</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <nav class="flex gap-1" aria-label="週">
                    @foreach ([0 => '今週', 1 => '来週'] as $value => $label)
                        <a href="{{ route('dashboard', ['week' => $value]) }}"
                           class="px-4 py-2 rounded-md text-sm {{ $week === $value ? 'bg-gray-800 text-white font-semibold' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
                @if ($lastUpdated)
                    <p class="text-xs text-gray-500">
                        この週のシフトの最終更新：{{ DateLabel::dateTime($lastUpdated->updated_at) }}（{{ $lastUpdated->updatedBy?->name ?? '不明' }}）
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap gap-4 text-xs text-gray-600">
                <span><span class="inline-block w-3 h-3 align-middle bg-sky-50 border border-gray-300"></span> 中高生</span>
                <span><span class="inline-block w-3 h-3 align-middle bg-amber-50 border border-gray-300"></span> 小学生（上＝1/2、下＝2/2）</span>
                <span><span class="inline-block w-3 h-3 align-middle bg-violet-50 border border-gray-300"></span> 小学生集団</span>
                <span><span class="text-indigo-500">●</span> 時間割から個別に変更</span>
                <span><span class="inline-block w-3 h-3 align-middle ring-2 ring-inset ring-amber-400"></span> 代講を募集中</span>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="border-collapse text-xs">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-20 bg-gray-50 border border-gray-200 px-2 py-2 text-left min-w-[4rem]">限</th>
                            @foreach ($days as $day)
                                <th colspan="2" class="border border-gray-200 px-2 py-1 min-w-[12rem] {{ $day['date']->isToday() ? 'bg-indigo-100' : 'bg-gray-50' }}">
                                    <div class="text-sm font-bold text-gray-900">
                                        {{ DateLabel::date($day['date']) }}
                                        @if ($day['date']->isToday())
                                            <span class="ml-1 rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">今日</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] font-normal text-gray-500">
                                        @if ($day['closed'])
                                            休み{{ $day['closed']->note ? '（'.$day['closed']->note.'）' : '' }}
                                        @else
                                            {{ $day['pattern']?->label() ?? '休み' }}
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @foreach ($row['cells'] as $rowIndex => $cells)
                                <tr class="{{ count($row['cells']) === 2 ? 'h-12' : 'h-24' }}">
                                    @if ($rowIndex === 0)
                                        <th rowspan="{{ count($row['cells']) }}" class="sticky left-0 z-10 bg-gray-50 border border-gray-200 px-2 py-2 text-left align-top">
                                            <div class="text-sm font-bold text-gray-900">{{ $row['period'] }}限</div>
                                        </th>
                                    @endif
                                    @foreach ($cells as $cell)
                                        <td rowspan="{{ $cell['rowspan'] }}" colspan="{{ $cell['colspan'] }}"
                                            @if ($cell['time']) title="{{ $cell['time'] }}" @endif
                                            @if ($cell['requested']) data-request="active" @endif
                                            class="border border-gray-200 px-1 {{ count($row['cells']) === 2 ? 'h-12' : 'h-24' }} w-24 min-w-[6rem] text-center align-middle whitespace-nowrap {{ $cell['label'] !== '' ? $cellClass[$cell['type']] : ($cell['type'] === 'none' ? 'bg-gray-100' : 'bg-white') }} {{ $cell['requested'] ? 'ring-2 ring-inset ring-amber-400' : '' }}">
                                            {{ $cell['label'] }}
                                            @if ($cell['manual'])
                                                <span class="text-indigo-500" title="時間割から個別に変更">●</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (collect($days)->every(fn ($day) => $day['shifts']->isEmpty()))
                <p class="text-sm text-gray-600">この週はシフトがありません。</p>
            @endif
        </div>
    </div>
</x-app-layout>
