@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            時間割{{ $term ? '（'.$term->label().'）' : '' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($term === null)
                <div class="rounded-md bg-gray-50 p-6 text-sm text-gray-700">まだ時間割が登録されていません。</div>
            @else
                <p class="text-sm text-gray-600">
                    {{ DateLabel::date($term->start_date) }}〜{{ DateLabel::date($term->end_date) }}
                    ／ 学期中の基本の時間割です（日ごとの変更や代講はシフトで確認してください）
                </p>

                <nav class="flex gap-1 overflow-x-auto" aria-label="曜日">
                    @foreach (range(1, 6) as $day)
                        <a href="{{ route('timetable', ['weekday' => $day]) }}"
                           class="px-4 py-2 rounded-md text-sm whitespace-nowrap {{ $day === $weekday ? 'bg-gray-800 text-white font-semibold' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
                            {{ DateLabel::weekday($day) }}曜
                        </a>
                    @endforeach
                </nav>

                @include('partials.timetable-grid', ['editable' => false, 'highlightUserId' => auth()->id()])
            @endif
        </div>
    </div>
</x-app-layout>
