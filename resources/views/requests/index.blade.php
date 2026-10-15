@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">募集中の代講</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <p class="text-sm text-gray-600">授業の日時が近い順に並んでいます。</p>

            <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                @forelse ($requests as $item)
                    <a href="{{ route('requests.show', $item) }}" class="flex flex-wrap items-center gap-4 px-4 py-3 text-sm hover:bg-indigo-50">
                        <span class="w-28 font-semibold text-gray-900">{{ DateLabel::date($item->shift->date) }}</span>
                        <span class="w-36 text-gray-700">{{ $item->shift->timeSlot->name }} {{ $item->shift->timeSlot->timeRange() }}</span>
                        <span class="flex-1 text-gray-900">{{ $item->shift->lessonLabel() }}</span>
                        <span class="text-gray-600">{{ $item->requester->displayName() }}</span>
                        <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $item->status->label() }}</span>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-gray-500">いま募集中の代講はありません。</p>
                @endforelse
            </div>

            <div>{{ $requests->links() }}</div>
        </div>
    </div>
</x-app-layout>
