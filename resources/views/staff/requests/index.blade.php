@php use App\Enums\RequestStatus; use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">代講依頼の状況</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('staff.requests.index') }}" class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap items-end gap-3 text-sm">
                <div>
                    <label for="status" class="block text-gray-600">状態</label>
                    <select id="status" name="status" class="mt-1 rounded-md border-gray-300 text-sm">
                        <option value="active" @selected($status === 'active')>未確定（募集中・応募あり）</option>
                        @foreach (RequestStatus::cases() as $case)
                            <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                        @endforeach
                        <option value="all" @selected($status === 'all')>すべて</option>
                    </select>
                </div>
                <div>
                    <label for="date" class="block text-gray-600">授業の日付</label>
                    <input id="date" type="date" name="date" value="{{ $date }}" class="mt-1 rounded-md border-gray-300 text-sm">
                </div>
                <x-primary-button>絞り込む</x-primary-button>
                <p class="w-full text-xs text-gray-500">日付を指定しないときは、1週間前以降の授業を表示します。</p>
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-2 font-medium whitespace-nowrap">授業</th>
                            <th class="px-4 py-2 font-medium whitespace-nowrap">内容</th>
                            <th class="px-4 py-2 font-medium whitespace-nowrap">依頼した先生</th>
                            <th class="px-4 py-2 font-medium whitespace-nowrap">応募</th>
                            <th class="px-4 py-2 font-medium whitespace-nowrap">代講する先生</th>
                            <th class="px-4 py-2 font-medium whitespace-nowrap">状態</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($requests as $item)
                            <tr class="hover:bg-indigo-50">
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <a href="{{ route('requests.show', $item) }}" class="font-semibold text-indigo-700 hover:underline">
                                        {{ DateLabel::date($item->shift->date) }} {{ $item->shift->timeSlot->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $item->shift->lessonLabel() }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $item->requester->displayName() }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $item->status->isActive() ? $item->pending_count.' 人' : '—' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $item->substitute?->displayName() ?? '—' }}</td>
                                <td class="px-4 py-2"><x-request-status :status="$item->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">該当する代講依頼はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $requests->links() }}</div>
        </div>
    </div>
</x-app-layout>
