@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">募集中の代講</h2>
            @include('requests.partials.tabs')
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('requests.index') }}" class="bg-white shadow-sm rounded-lg p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end text-sm">
                <div>
                    <label for="date" class="block text-gray-600">日付</label>
                    <input id="date" type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="level" class="block text-gray-600">生徒</label>
                    <select id="level" name="level" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="">すべて</option>
                        <option value="secondary" @selected(($filters['level'] ?? '') === 'secondary')>中高生</option>
                        <option value="elementary" @selected(($filters['level'] ?? '') === 'elementary')>小学生</option>
                    </select>
                </div>
                @if (auth()->user()->isTeacher())
                    <label class="flex items-center gap-2 py-2">
                        <input type="checkbox" name="mine" value="1" @checked(! empty($filters['mine'])) class="rounded border-gray-300 text-indigo-600">
                        <span class="text-gray-700">担当できる科目だけ</span>
                    </label>
                @endif
                <div class="flex gap-2">
                    <x-primary-button class="justify-center">絞り込む</x-primary-button>
                    @if (array_filter($filters))
                        <a href="{{ route('requests.index') }}" class="self-center text-gray-600 hover:text-gray-900">解除</a>
                    @endif
                </div>
            </form>

            <p class="text-sm text-gray-600">授業の日時が近い順に並んでいます（{{ $requests->total() }} 件）。</p>

            <div class="space-y-3">
                @forelse ($requests as $item)
                    <a href="{{ route('requests.show', $item) }}" class="block bg-white shadow-sm rounded-lg p-4 hover:ring-2 hover:ring-indigo-400 transition">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-gray-900">
                                {{ DateLabel::date($item->shift->date) }}
                                <span class="ml-1">{{ $item->shift->timeSlot->name }}</span>
                                <span class="ml-1 text-sm font-normal text-gray-500">{{ $item->shift->timeSlot->timeRange() }}</span>
                            </p>
                            <div class="flex items-center gap-2">
                                @if ($item->requester_id === auth()->id())
                                    <span class="rounded bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">あなたの依頼</span>
                                @elseif ($item->applied_by_me)
                                    <span class="rounded bg-indigo-600 px-2 py-0.5 text-xs text-white">応募済み</span>
                                @endif
                                <x-request-status :status="$item->status" />
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="text-gray-900">{{ $item->shift->lessonLabel() }}</span>
                            <span class="text-gray-600">依頼：{{ $item->requester->displayName() }}・応募 {{ $item->pending_count }} 人</span>
                        </div>
                    </a>
                @empty
                    <p class="bg-white shadow-sm rounded-lg px-4 py-6 text-center text-sm text-gray-500">
                        {{ array_filter($filters) ? '条件に合う代講はありません。' : 'いま募集中の代講はありません。' }}
                    </p>
                @endforelse
            </div>

            <div>{{ $requests->links() }}</div>
        </div>
    </div>
</x-app-layout>
