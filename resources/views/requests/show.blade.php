@php
    use App\Support\DateLabel;
    $shift = $substituteRequest->shift;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">代講依頼の詳細</h2>
            <a href="{{ route('requests.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← 募集中の代講</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xl font-bold text-gray-900">{{ DateLabel::date($shift->date) }} {{ $shift->timeSlot->name }}</p>
                    <span class="rounded bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">{{ $substituteRequest->status->label() }}</span>
                </div>

                <dl class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-gray-500">時間</dt>
                    <dd class="col-span-2 text-gray-900">{{ $shift->timeSlot->timeRange() }}</dd>
                    <dt class="text-gray-500">授業</dt>
                    <dd class="col-span-2 text-gray-900">{{ $shift->lessonLabel() }}</dd>
                    <dt class="text-gray-500">依頼した先生</dt>
                    <dd class="col-span-2 text-gray-900">
                        {{ $substituteRequest->requester->displayName() }}
                        @if ($substituteRequest->requester->nickname)
                            <span class="text-gray-500">（{{ $substituteRequest->requester->surnameLabel() }}）</span>
                        @endif
                    </dd>
                    <dt class="text-gray-500">理由</dt>
                    <dd class="col-span-2 text-gray-900">{{ $substituteRequest->reason ?: '—' }}</dd>
                    <dt class="text-gray-500">依頼した日時</dt>
                    <dd class="col-span-2 text-gray-900">{{ DateLabel::dateTime($substituteRequest->created_at) }}</dd>
                </dl>

                <div>
                    <p class="text-sm text-gray-500">引き継ぎメモ</p>
                    <p class="mt-1 whitespace-pre-wrap rounded-md bg-gray-50 p-3 text-sm text-gray-900">{{ $substituteRequest->handover_note }}</p>
                </div>

                <p class="text-xs text-gray-500">応募・代講者の確定は、これから追加します（Week 23）。</p>
            </div>
        </div>
    </div>
</x-app-layout>
