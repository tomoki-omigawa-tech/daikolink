@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">時間割（学期）</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">学期</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">期間</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">授業数</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($terms as $term)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $term->label() }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ DateLabel::date($term->start_date) }}〜{{ DateLabel::date($term->end_date) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $term->lessons_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('staff.terms.timetable', $term) }}" class="text-indigo-600 hover:text-indigo-800">時間割を見る</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">まだ学期がありません</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $terms->links() }}</div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800">新しい学期を作る</h3>
                <form method="POST" action="{{ route('staff.terms.store') }}" class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 items-end">
                    @csrf
                    <div>
                        <x-input-label for="fiscal_year" value="年度" />
                        <x-text-input id="fiscal_year" name="fiscal_year" type="number" class="mt-1 block w-full" :value="old('fiscal_year', now()->month >= 4 ? now()->year : now()->year - 1)" required />
                        <x-input-error :messages="$errors->get('fiscal_year')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="term_no" value="学期" />
                        <select id="term_no" name="term_no" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @foreach ([1, 2, 3] as $no)
                                <option value="{{ $no }}" @selected((string) old('term_no') === (string) $no)>{{ $no }}学期</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('term_no')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="start_date" value="開始日" />
                        <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date')" required />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="end_date" value="終了日" />
                        <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date')" required />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                    </div>
                    <div class="col-span-2 sm:col-span-4">
                        <x-primary-button>作成する</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
