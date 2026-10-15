@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">代講を依頼する</h2>
            <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900">← マイシフト</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('requests.store') }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                <div>
                    <p class="text-lg font-semibold text-gray-900">{{ DateLabel::date($date) }}のあなたの授業</p>
                    <p class="mt-1 text-sm text-gray-600">代講をお願いしたい授業を選んでください（いくつでも選べます。選んだ授業ごとに依頼が出ます）。</p>
                </div>

                @if ($errors->has('shift_ids') || $errors->has('shift_ids.*'))
                    <ul class="rounded-md bg-red-50 p-3 text-sm text-red-700 list-disc pl-6">
                        @foreach (array_merge($errors->get('shift_ids'), $errors->get('shift_ids.*')) as $messages)
                            @foreach ((array) $messages as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                @endif

                <div class="divide-y divide-gray-100 rounded-md border border-gray-200">
                    @forelse ($shifts as $shift)
                        @php
                            $requested = $shift->activeRequests->isNotEmpty();
                            $started = ! $shift->startsAt()->isFuture();
                            $checked = in_array((string) $shift->id, array_map('strval', (array) old('shift_ids', [$selected])), true);
                        @endphp
                        <label class="flex items-center gap-3 px-4 py-3 text-sm {{ $requested || $started ? 'bg-gray-50 text-gray-400' : 'cursor-pointer hover:bg-indigo-50' }}">
                            <input type="checkbox" name="shift_ids[]" value="{{ $shift->id }}" class="rounded border-gray-300 text-indigo-600"
                                   @checked($checked && ! $requested && ! $started) @disabled($requested || $started)>
                            <span class="w-24 font-semibold">{{ $shift->timeSlot->name }}</span>
                            <span class="w-28">{{ $shift->timeSlot->timeRange() }}</span>
                            <span class="flex-1">{{ $shift->lessonLabel() }}</span>
                            @if ($requested)
                                <span class="text-xs">依頼済み</span>
                            @elseif ($started)
                                <span class="text-xs">授業が始まっています</span>
                            @endif
                        </label>
                    @empty
                        <p class="px-4 py-3 text-sm text-gray-600">この日の授業はありません。</p>
                    @endforelse
                </div>

                <div>
                    <x-input-label for="reason" value="理由（任意・ほかの先生にも見えます）" />
                    <x-text-input id="reason" name="reason" type="text" class="mt-1 block w-full" :value="old('reason')" maxlength="255" placeholder="例：大学の試験のため" />
                    <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="handover_note" value="引き継ぎメモ（必須・1000文字まで）" />
                    <textarea id="handover_note" name="handover_note" rows="5" maxlength="1000" required
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="例：教科書p.52〜の続き。宿題のワークp.30の丸付けをお願いします。">{{ old('handover_note') }}</textarea>
                    <x-input-error :messages="$errors->get('handover_note')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>代講を依頼する</x-primary-button>
                    <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
