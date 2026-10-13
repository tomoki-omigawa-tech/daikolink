@php use App\Support\DateLabel; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $term->label() }}の時間割</h2>
            <a href="{{ route('staff.terms.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← 学期の一覧</a>
        </div>
    </x-slot>

    <style>[x-cloak] { display: none !important; }</style>
    <script>
        window.timetableEditor = (config) => ({
            config,
            open: false,
            saving: false,
            errors: [],
            pos: { top: 0, left: 0 },
            target: { userId: null, period: null, seat: 1 },
            mode: 'secondary',
            secondary: { grade: '', subject_id: '' },
            first: { grade: '', subject_id: '' },
            second: { grade: '', subject_id: '' },

            init() {
                const saved = sessionStorage.getItem(this.scrollKey());
                if (saved) {
                    const [left, top] = saved.split(',').map(Number);
                    this.$refs.scroller.scrollLeft = left;
                    window.scrollTo(0, top);
                    sessionStorage.removeItem(this.scrollKey());
                }
            },
            scrollKey() {
                return 'timetable-scroll:' + location.pathname + location.search;
            },
            periodInfo() {
                return this.config.periods[this.target.period] ?? { hasElementary: false, hasGroup: false };
            },
            teacher() {
                return this.config.teachers[this.target.userId] ?? { name: '', subjects: [] };
            },
            title() {
                return `${this.teacher().name}　${this.target.period}限・${this.target.seat === 1 ? '左' : '右'}の席`;
            },
            edit(event, userId, period, seat) {
                const state = this.config.state[`${userId}-${period}-${seat}`] ?? {};
                this.target = { userId, period, seat };
                this.mode = state.mode && state.mode !== 'empty' ? state.mode : 'secondary';
                this.secondary = { grade: '', subject_id: '', ...(state.secondary ?? {}) };
                this.first = { grade: '', subject_id: '', ...(state.first ?? {}) };
                this.second = { grade: '', subject_id: '', ...(state.second ?? {}) };
                this.errors = [];

                const rect = event.currentTarget.getBoundingClientRect();
                const width = 340;
                const height = 320;
                this.pos.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
                this.pos.top = rect.bottom + height < window.innerHeight ? rect.bottom + 4 : Math.max(8, rect.top - height - 4);
                this.open = true;
            },
            grades(elementary) {
                return this.config.grades.filter((grade) => grade.elementary === elementary);
            },
            subjectsFor(gradeValue) {
                const grade = this.config.grades.find((g) => g.value === gradeValue);
                return grade ? this.config.subjects.filter((s) => grade.stages.includes(s.stage)) : [];
            },
            resetSubject(lesson) {
                if (!this.subjectsFor(lesson.grade).some((s) => s.id === lesson.subject_id)) {
                    lesson.subject_id = '';
                }
            },
            warn(subjectId) {
                return subjectId !== '' && !this.teacher().subjects.includes(subjectId);
            },
            async save(mode = this.mode) {
                this.saving = true;
                this.errors = [];
                const response = await fetch(this.config.updateUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        weekday: this.config.weekday,
                        user_id: this.target.userId,
                        period: this.target.period,
                        seat: this.target.seat,
                        mode,
                        secondary: this.secondary,
                        first: this.first,
                        second: this.second,
                    }),
                });
                this.saving = false;

                if (response.ok) {
                    sessionStorage.setItem(this.scrollKey(), `${this.$refs.scroller.scrollLeft},${window.scrollY}`);
                    location.reload();
                    return;
                }
                const json = await response.json().catch(() => ({}));
                this.errors = json.errors ? Object.values(json.errors).flat() : ['保存できませんでした。'];
            },
        });
    </script>

    <div class="py-8" x-data="timetableEditor(@js($editorConfig))">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="flex flex-wrap items-end justify-between gap-4">
                <p class="text-sm text-gray-600">
                    {{ DateLabel::date($term->start_date) }}〜{{ DateLabel::date($term->end_date) }}
                    ／ マスをクリックすると入力できます
                </p>
                <form method="POST" action="{{ route('staff.terms.apply', $term) }}" class="flex items-end gap-2"
                      onsubmit="return confirm('この日から学期末までのシフトを時間割どおりに作り直します。個別に変更したシフトと代講依頼があるシフトは残します。よろしいですか？')">
                    @csrf
                    <div>
                        <x-input-label for="from" value="この日からシフトに反映" />
                        <x-text-input id="from" name="from" type="date" class="mt-1" :value="today()->toDateString()" required />
                    </div>
                    <x-primary-button>反映する</x-primary-button>
                </form>
            </div>

            <nav class="flex gap-1 overflow-x-auto" aria-label="曜日">
                @foreach (range(1, 6) as $day)
                    <a href="{{ route('staff.terms.timetable', [$term, 'weekday' => $day]) }}"
                       class="px-4 py-2 rounded-md text-sm whitespace-nowrap {{ $day === $weekday ? 'bg-gray-800 text-white font-semibold' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
                        {{ DateLabel::weekday($day) }}曜
                    </a>
                @endforeach
            </nav>

            @include('partials.timetable-grid', ['editable' => true])
        </div>

        {{-- 入力枠 --}}
        <div x-show="open" x-cloak @click.outside="open = false" @keydown.escape.window="open = false"
             class="fixed z-50 w-[340px] rounded-lg bg-white p-4 text-sm shadow-xl border border-gray-200 space-y-3"
             :style="`top: ${pos.top}px; left: ${pos.left}px`">
            <p class="font-semibold text-gray-900" x-text="title()"></p>

            <div class="flex gap-3" x-show="periodInfo().hasElementary || periodInfo().hasGroup">
                <label class="inline-flex items-center gap-1"><input type="radio" value="secondary" x-model="mode"> 中高生</label>
                <label class="inline-flex items-center gap-1" x-show="periodInfo().hasElementary"><input type="radio" value="elementary" x-model="mode"> 小学生</label>
                <label class="inline-flex items-center gap-1" x-show="periodInfo().hasGroup"><input type="radio" value="group" x-model="mode"> 小学生集団</label>
            </div>

            <div x-show="mode === 'secondary'" class="flex gap-2">
                <select x-model="secondary.grade" @change="resetSubject(secondary)" class="rounded-md border-gray-300 py-1 text-sm">
                    <option value="">学年</option>
                    <template x-for="grade in grades(false)" :key="grade.value">
                        <option :value="grade.value" x-text="grade.label" :selected="grade.value === secondary.grade"></option>
                    </template>
                </select>
                <select x-model="secondary.subject_id" class="flex-1 rounded-md border-gray-300 py-1 text-sm">
                    <option value="">科目</option>
                    <template x-for="subject in subjectsFor(secondary.grade)" :key="subject.id">
                        <option :value="subject.id" x-text="subject.name" :selected="subject.id === secondary.subject_id"></option>
                    </template>
                </select>
            </div>
            <p x-show="mode === 'secondary' && warn(secondary.subject_id)" class="text-xs text-amber-700">⚠ 担当できる科目に入っていません</p>

            <template x-for="half in [{ key: 'first', label: '1/2' }, { key: 'second', label: '2/2' }]" :key="half.key">
                <div x-show="mode === 'elementary'">
                    <div class="flex items-center gap-2">
                        <span class="w-8 text-gray-600" x-text="half.label"></span>
                        <select x-model="$data[half.key].grade" @change="resetSubject($data[half.key])" class="rounded-md border-gray-300 py-1 text-sm">
                            <option value="">なし</option>
                            <template x-for="grade in grades(true)" :key="grade.value">
                                <option :value="grade.value" x-text="grade.label" :selected="grade.value === $data[half.key].grade"></option>
                            </template>
                        </select>
                        <select x-model="$data[half.key].subject_id" class="flex-1 rounded-md border-gray-300 py-1 text-sm">
                            <option value="">科目</option>
                            <template x-for="subject in subjectsFor($data[half.key].grade)" :key="subject.id">
                                <option :value="subject.id" x-text="subject.name" :selected="subject.id === $data[half.key].subject_id"></option>
                            </template>
                        </select>
                    </div>
                    <p x-show="warn($data[half.key].subject_id)" class="mt-1 text-xs text-amber-700">⚠ 担当できる科目に入っていません</p>
                </div>
            </template>

            <p x-show="mode === 'group'" class="text-gray-600">この限は小学生集団を担当します（両方の席を使います）。</p>

            <ul x-show="errors.length" class="rounded-md bg-red-50 p-2 text-xs text-red-700 list-disc pl-5">
                <template x-for="error in errors" :key="error">
                    <li x-text="error"></li>
                </template>
            </ul>

            <div class="flex items-center gap-2">
                <x-primary-button type="button" @click="save()" x-bind:disabled="saving">保存</x-primary-button>
                <x-secondary-button type="button" @click="save('empty')" x-bind:disabled="saving">空にする</x-secondary-button>
                <button type="button" @click="open = false" class="ml-auto text-gray-500 hover:text-gray-800">閉じる</button>
            </div>
        </div>
    </div>
</x-app-layout>
