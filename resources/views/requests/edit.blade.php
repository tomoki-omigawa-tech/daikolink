@php use App\Support\DateLabel; $shift = $substituteRequest->shift; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">代講依頼を編集する</h2>
            <a href="{{ route('requests.show', $substituteRequest) }}" class="text-sm text-gray-600 hover:text-gray-900">← 依頼の詳細</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('requests.update', $substituteRequest) }}" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <p class="text-lg font-semibold text-gray-900">{{ DateLabel::date($shift->date) }} {{ $shift->timeSlot->name }}（{{ $shift->timeSlot->timeRange() }}）</p>
                    <p class="mt-1 text-sm text-gray-600">{{ $shift->lessonLabel() }}</p>
                </div>

                <x-input-error :messages="$errors->get('request')" />

                <div>
                    <x-input-label for="reason" value="理由（任意・ほかの先生にも見えます）" />
                    <x-text-input id="reason" name="reason" type="text" class="mt-1 block w-full" :value="old('reason', $substituteRequest->reason)" maxlength="255" />
                    <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="handover_note" value="引き継ぎメモ（必須・1000文字まで）" />
                    <textarea id="handover_note" name="handover_note" rows="6" maxlength="1000" required
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('handover_note', $substituteRequest->handover_note) }}</textarea>
                    <x-input-error :messages="$errors->get('handover_note')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>更新する</x-primary-button>
                    <a href="{{ route('requests.show', $substituteRequest) }}" class="text-sm text-gray-600 hover:text-gray-900">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
