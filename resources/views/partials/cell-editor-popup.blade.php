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
