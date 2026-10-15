@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">シフト管理</h2>
            <a href="{{ route('staff.terms.index') }}" class="text-sm text-gray-600 hover:text-gray-900">学期の時間割 →</a>
        </div>
    </x-slot>

    @include('partials.cell-editor-script')

    <div class="py-8" @if ($editorConfig) x-data="cellEditor(@js($editorConfig))" @endif>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="GET" action="{{ route('staff.shifts.index') }}" class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap items-end gap-3">
                <a href="{{ route('staff.shifts.index', ['date' => $date->copy()->subDay()->toDateString()]) }}"
                   class="px-3 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">← 前の日</a>
                <div>
                    <x-input-label for="date" value="日付" />
                    <x-text-input id="date" name="date" type="date" class="mt-1" :value="$date->toDateString()" />
                </div>
                <a href="{{ route('staff.shifts.index', ['date' => $date->copy()->addDay()->toDateString()]) }}"
                   class="px-3 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">次の日 →</a>
                <div>
                    <x-input-label for="pattern" value="時間割" />
                    <select id="pattern" name="pattern" class="mt-1 rounded-md border-gray-300 shadow-sm">
                        @foreach ($patterns as $option)
                            <option value="{{ $option->value }}" @selected($pattern === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button>表示</x-primary-button>
                <p class="w-full text-sm text-gray-600">
                    <span class="font-semibold text-gray-900">{{ DateLabel::date($date) }}</span>
                    @if ($pattern) ／ {{ $pattern->label() }}の時間割 @endif
                    @if ($lastUpdated)
                        ／ 最終更新：{{ DateLabel::dateTime($lastUpdated->updated_at) }}（{{ $lastUpdated->updatedBy?->name ?? '不明' }}）
                    @endif
                </p>
            </form>

            @if ($closedDay)
                <div class="rounded-md bg-gray-100 p-6 text-sm text-gray-700 flex flex-wrap items-center justify-between gap-4">
                    <p>この日は休みです{{ $closedDay->note ? '（'.$closedDay->note.'）' : '' }}。</p>
                    <form method="POST" action="{{ route('staff.shifts.reopen') }}"
                          onsubmit="return confirm('休みを解除して、時間割からこの日のシフトを作り直します。よろしいですか？')">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                        <x-secondary-button type="submit">休みを解除する</x-secondary-button>
                    </form>
                </div>
            @elseif ($pattern === null)
                <div class="rounded-md bg-gray-100 p-6 text-sm text-gray-700">
                    日曜は休みです。講習の日は、時間割で「講習」を選んで「表示」を押してください。
                </div>
            @else
                @if ($existingPattern && $existingPattern !== $pattern)
                    <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-800 flex flex-wrap items-center justify-between gap-4">
                        <p>この日はすでに「{{ $existingPattern->label() }}」の時間割でシフトがあります。「{{ $pattern->label() }}」に切り替えるには、先にこの日のシフトをすべて消してください。</p>
                        <form method="POST" action="{{ route('staff.shifts.clear') }}"
                              onsubmit="return confirm('この日のシフトをすべて消します（代講依頼があると消せません）。よろしいですか？')">
                            @csrf
                            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                            <input type="hidden" name="pattern" value="{{ $pattern->value }}">
                            <x-danger-button type="submit">この日のシフトをすべて消す</x-danger-button>
                        </form>
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600">マスをクリックすると、その日だけのシフトを入力できます（時間割は変わりません）。</p>
                    <form method="POST" action="{{ route('staff.shifts.close') }}" class="flex items-end gap-2"
                          onsubmit="return confirm('この日のシフトをすべて消して、休みにします。よろしいですか？')">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                        <div>
                            <x-input-label for="note" value="休みのメモ（任意）" />
                            <x-text-input id="note" name="note" type="text" class="mt-1 w-40" maxlength="50" placeholder="例：年末年始" />
                        </div>
                        <x-secondary-button type="submit">この日を休みにする</x-secondary-button>
                    </form>
                </div>

                @include('partials.timetable-grid', ['editable' => true, 'showManual' => true])
            @endif
        </div>

        @if ($editorConfig && ! $closedDay)
            @include('partials.cell-editor-popup')
        @endif
    </div>
</x-app-layout>
