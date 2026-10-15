@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $term->label() }}の時間割</h2>
            <a href="{{ route('staff.terms.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← 学期の一覧</a>
        </div>
    </x-slot>

    @include('partials.cell-editor-script')

    <div class="py-8" x-data="cellEditor(@js($editorConfig))">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="flex flex-wrap items-end justify-between gap-4">
                <p class="text-sm text-gray-600">
                    {{ DateLabel::date($term->start_date) }}〜{{ DateLabel::date($term->end_date) }}
                    ／ マスをクリックすると入力できます
                </p>
                <form method="POST" action="{{ route('staff.terms.apply', $term) }}" class="flex items-end gap-2"
                      onsubmit="return confirm('この日から学期末までのシフトを時間割どおりに作り直します。個別に変更したシフトと代講依頼があるシフトは残します。よろしいですか？')">
                    @csrf
                    <div>
                        <x-input-label for="from" value="この日からシフトに反映" />
                        <x-text-input id="from" name="from" type="date" class="mt-1" :value="today()->toDateString()" required />
                    </div>
                    <x-primary-button>反映する</x-primary-button>
                </form>
            </div>

            <nav class="flex gap-1 overflow-x-auto" aria-label="曜日">
                @foreach (range(1, 6) as $day)
                    <a href="{{ route('staff.terms.timetable', [$term, 'weekday' => $day]) }}"
                       class="px-4 py-2 rounded-md text-sm whitespace-nowrap {{ $day === $weekday ? 'bg-gray-800 text-white font-semibold' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
                        {{ DateLabel::weekday($day) }}曜
                    </a>
                @endforeach
            </nav>

            @include('partials.timetable-grid', ['editable' => true])
        </div>

        @include('partials.cell-editor-popup')
    </div>
</x-app-layout>
