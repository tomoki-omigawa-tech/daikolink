@php
    $cellClass = [
        'secondary' => 'bg-sky-50',
        'first' => 'bg-amber-50',
        'second' => 'bg-amber-50',
        'group' => 'bg-violet-50',
        'empty' => 'bg-white',
    ];
    $editable = $editable ?? false;
    $highlightUserId = $highlightUserId ?? null;
@endphp

<div class="flex flex-wrap gap-4 text-xs text-gray-600">
    <span><span class="inline-block w-3 h-3 align-middle bg-sky-50 border border-gray-300"></span> 中高生</span>
    <span><span class="inline-block w-3 h-3 align-middle bg-amber-50 border border-gray-300"></span> 小学生（上＝1/2、下＝2/2）</span>
    <span><span class="inline-block w-3 h-3 align-middle bg-violet-50 border border-gray-300"></span> 小学生集団</span>
    <span>⚠ 担当できる科目に入っていない</span>
</div>

<div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto" x-ref="scroller">
    <table class="border-collapse text-xs">
        <thead>
            <tr>
                <th class="sticky left-0 z-20 bg-gray-50 border border-gray-200 px-2 py-2 text-left min-w-[5rem]">限</th>
                @foreach ($teachers as $teacher)
                    <th colspan="2" class="border border-gray-200 px-2 py-1 min-w-[12rem] {{ $teacher->id === $highlightUserId ? 'bg-indigo-100' : 'bg-gray-50' }}">
                        <div class="text-sm font-bold text-gray-900">
                            {{ $teacher->displayName() }}
                            @if ($teacher->id === $highlightUserId)
                                <span class="ml-1 rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">あなた</span>
                            @endif
                        </div>
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
                                @if ($editable) @click.stop="edit($event, {{ $cell['teacher_id'] }}, {{ $cell['period'] }}, {{ $cell['seat'] }})" @endif
                                class="{{ $editable ? 'cursor-pointer hover:ring-2 hover:ring-inset hover:ring-indigo-400' : '' }} border border-gray-200 px-1 {{ count($period['rows']) === 2 ? 'h-12' : 'h-24' }} w-24 min-w-[6rem] text-center align-middle whitespace-nowrap {{ $cell['lesson'] ? $cellClass[$cell['type']] : 'bg-white' }}">
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
