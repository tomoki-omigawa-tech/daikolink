@php
    use App\Enums\RequestStatus;
    use App\Support\DateLabel;
    $shift = $substituteRequest->shift;
    $isRequester = $substituteRequest->requester_id === auth()->id();
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">代講依頼の詳細</h2>
            <a href="{{ $isRequester ? route('requests.mine') : route('requests.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                ← {{ $isRequester ? '自分の依頼・応募' : '募集中の代講' }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-5"
                 x-data="{
                    applied: @js($appliedByMe),
                    count: @js($pending->count()),
                    statusLabel: @js($substituteRequest->status->label()),
                    message: '',
                    error: '',
                    sending: false,
                    async toggleApply() {
                        if (this.sending) return;
                        this.sending = true;
                        this.message = '';
                        this.error = '';
                        try {
                            const response = await fetch(@js(route('requests.apply', $substituteRequest)), {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                },
                            });
                            const data = await response.json();
                            if (!response.ok) {
                                this.error = data.message ?? '処理できませんでした。ページを再読み込みしてください。';
                                return;
                            }
                            this.applied = data.applied;
                            this.count = data.count;
                            this.statusLabel = data.status;
                            this.message = data.message;
                        } catch (e) {
                            this.error = '通信に失敗しました。もう一度お試しください。';
                        } finally {
                            this.sending = false;
                        }
                    },
                 }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xl font-bold text-gray-900">{{ DateLabel::date($shift->date) }} {{ $shift->timeSlot->name }}</p>
                    @if ($substituteRequest->status->isActive())
                        <span class="rounded px-2 py-0.5 text-xs font-semibold"
                              :class="count > 0 ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800'"
                              x-text="statusLabel">{{ $substituteRequest->status->label() }}</span>
                    @else
                        <x-request-status :status="$substituteRequest->status" />
                    @endif
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
                    @if ($substituteRequest->status === RequestStatus::Confirmed)
                        <dt class="text-gray-500">代講する先生</dt>
                        <dd class="col-span-2 font-semibold text-green-800">{{ $substituteRequest->substitute?->displayName() }}</dd>
                        <dt class="text-gray-500">確定した日時</dt>
                        <dd class="col-span-2 text-gray-900">{{ DateLabel::dateTime($substituteRequest->confirmed_at) }}</dd>
                    @endif
                    <dt class="text-gray-500">理由</dt>
                    <dd class="col-span-2 text-gray-900">{{ $substituteRequest->reason ?: '—' }}</dd>
                    <dt class="text-gray-500">依頼した日時</dt>
                    <dd class="col-span-2 text-gray-900">{{ DateLabel::dateTime($substituteRequest->created_at) }}</dd>
                    @if ($substituteRequest->status->isActive())
                        <dt class="text-gray-500">応募</dt>
                        <dd class="col-span-2 text-gray-900"><span x-text="count">{{ $pending->count() }}</span> 人</dd>
                    @endif
                </dl>

                <div>
                    <p class="text-sm text-gray-500">引き継ぎメモ</p>
                    <p class="mt-1 whitespace-pre-wrap rounded-md bg-gray-50 p-3 text-sm text-gray-900">{{ $substituteRequest->handover_note }}</p>
                </div>

                {{-- 応募ボタン（ページを再読み込みせずに切り替わる） --}}
                @can('apply', $substituteRequest)
                    <div class="border-t border-gray-100 pt-5 space-y-2">
                        <button type="button" @click="toggleApply()" :disabled="sending"
                                class="w-full sm:w-auto inline-flex justify-center items-center gap-2 rounded-md px-5 py-2.5 text-sm font-semibold transition disabled:opacity-50"
                                :class="applied ? 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50' : 'bg-indigo-600 text-white hover:bg-indigo-700'">
                            <span x-show="!applied">この代講に応募する</span>
                            <span x-show="applied" x-cloak>✓ 応募済み（押すと取り消し）</span>
                        </button>
                        <p x-show="message" x-text="message" class="text-sm text-green-700"></p>
                        <p x-show="error" x-text="error" class="text-sm text-red-600"></p>
                    </div>
                @endcan
            </div>

            {{-- 依頼した先生：応募者から代講者を確定する --}}
            @can('confirm', $substituteRequest)
                <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                    <h3 class="font-semibold text-gray-900">応募した先生（{{ $pending->count() }} 人）</h3>
                    @forelse ($pending as $application)
                        <form method="POST" action="{{ route('requests.confirm', $substituteRequest) }}"
                              class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 p-3"
                              x-data @submit="if (!confirm(@js($application->applicant->displayName().'さんに代講をお願いして確定します。よろしいですか？'))) $event.preventDefault()">
                            @csrf
                            <input type="hidden" name="application_id" value="{{ $application->id }}">
                            <div class="flex items-center gap-3">
                                @if ($application->applicant->avatarUrl())
                                    <img src="{{ $application->applicant->avatarUrl() }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                @endif
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $application->applicant->displayName() }}</p>
                                    <p class="text-xs text-gray-500">{{ DateLabel::dateTime($application->updated_at) }} に応募</p>
                                </div>
                            </div>
                            <x-primary-button>この先生で確定</x-primary-button>
                        </form>
                    @empty
                        <p class="text-sm text-gray-500">まだ応募はありません。応募があると、ここに表示されます。</p>
                    @endforelse
                </div>
            @endcan

            @can('update', $substituteRequest)
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('requests.edit', $substituteRequest) }}" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">理由・引き継ぎメモを編集</a>
                    <form method="POST" action="{{ route('requests.destroy', $substituteRequest) }}"
                          x-data @submit="if (!confirm('この代講依頼を取り消します。よろしいですか？')) $event.preventDefault()">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>依頼を取り消す</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
