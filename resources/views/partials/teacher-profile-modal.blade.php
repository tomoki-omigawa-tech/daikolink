<style>[x-cloak] { display: none !important; }</style>
<script>
    window.teacherProfile = (data) => ({
        data,
        open: false,
        teacher: null,
        show(id) {
            this.teacher = this.data.teachers[id] ?? null;
            this.open = this.teacher !== null;
        },
        subjectsOf(stage, can) {
            if (!this.teacher) {
                return [];
            }
            return this.data.subjects.filter((s) => s.stage === stage && this.teacher.subjects.includes(s.id) === can);
        },
    });
</script>

<div x-data="teacherProfile(@js($profiles))" x-on:show-profile.window="show($event.detail)" @keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="open = false">
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl space-y-4" x-show="teacher">
            <div class="flex items-center gap-4">
                <template x-if="teacher?.avatar">
                    <img :src="teacher.avatar" alt="" class="h-16 w-16 rounded-full object-cover border border-gray-200">
                </template>
                <template x-if="! teacher?.avatar">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-xl text-gray-400" x-text="teacher?.name.slice(0, 1)"></div>
                </template>
                <div>
                    <p class="text-lg font-bold text-gray-900" x-text="teacher?.name"></p>
                    <p class="text-sm text-gray-500" x-text="teacher?.surname"></p>
                </div>
                <button type="button" @click="open = false" class="ml-auto text-gray-400 hover:text-gray-700" aria-label="閉じる">✕</button>
            </div>

            <div class="space-y-3 text-sm">
                <template x-for="stage in data.stages" :key="stage.value">
                    <div x-show="data.subjects.some((s) => s.stage === stage.value)">
                        <p class="font-semibold text-gray-700" x-text="stage.label"></p>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <template x-for="subject in subjectsOf(stage.value, true)" :key="subject.id">
                                <span class="rounded bg-indigo-100 px-2 py-0.5 text-indigo-800" x-text="subject.name"></span>
                            </template>
                            <template x-for="subject in subjectsOf(stage.value, false)" :key="subject.id">
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-gray-400 line-through" x-text="subject.name"></span>
                            </template>
                        </div>
                    </div>
                </template>
                <p class="text-xs text-gray-500">色付き＝できる科目、灰色＝できない科目</p>
            </div>
        </div>
    </div>
</div>
