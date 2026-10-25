@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">自分の依頼・応募</h2>
            @include('requests.partials.tabs')
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <section class="space-y-3">
                <h3 class="font-semibold text-gray-900">自分が出した依頼</h3>
                @forelse ($requests as $item)
                    <a href="{{ route('requests.show', $item) }}" class="flex flex-wrap items-center justify-between gap-2 bg-white shadow-sm rounded-lg p-4 text-sm hover:ring-2 hover:ring-indigo-400">
                        <div>
                            <p class="font-semibold text-gray-900">{{ DateLabel::date($item->shift->date) }} {{ $item->shift->timeSlot->name }}</p>
                            <p class="text-gray-600">{{ $item->shift->lessonLabel() }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($item->status === \App\Enums\RequestStatus::Confirmed)
                                <span class="text-gray-700">代講：{{ $item->substitute?->displayName() }}</span>
                            @elseif ($item->status->isActive())
                                <span class="{{ $item->pending_count > 0 ? 'font-semibold text-sky-700' : 'text-gray-500' }}">応募 {{ $item->pending_count }} 人</span>
                            @endif
                            <x-request-status :status="$item->status" />
                        </div>
                    </a>
                @empty
                    <p class="bg-white shadow-sm rounded-lg px-4 py-6 text-center text-sm text-gray-500">最近の依頼はありません。</p>
                @endforelse
            </section>

            <section class="space-y-3">
                <h3 class="font-semibold text-gray-900">自分の応募</h3>
                @forelse ($applications as $application)
                    @php $req = $application->substituteRequest; @endphp
                    <a href="{{ route('requests.show', $req) }}" class="flex flex-wrap items-center justify-between gap-2 bg-white shadow-sm rounded-lg p-4 text-sm hover:ring-2 hover:ring-indigo-400">
                        <div>
                            <p class="font-semibold text-gray-900">{{ DateLabel::date($req->shift->date) }} {{ $req->shift->timeSlot->name }}</p>
                            <p class="text-gray-600">{{ $req->shift->lessonLabel() }}（依頼：{{ $req->requester->displayName() }}）</p>
                        </div>
                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $application->status === \App\Enums\ApplicationStatus::Accepted ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $application->status->label() }}</span>
                    </a>
                @empty
                    <p class="bg-white shadow-sm rounded-lg px-4 py-6 text-center text-sm text-gray-500">最近の応募はありません。</p>
                @endforelse
            </section>
        </div>
    </div>
</x-app-layout>
