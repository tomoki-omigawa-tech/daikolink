@php use App\Enums\SubjectStage; @endphp
<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">担当できる科目</h2>
        <p class="mt-1 text-sm text-gray-600">できる科目にチェックしてください。代講者を探すときに、ほかの先生からも見えます。</p>
    </header>

    <form method="post" action="{{ route('profile.subjects') }}" class="mt-6 space-y-4">
        @csrf
        @method('put')

        @php $checked = $user->subjects->pluck('id')->all(); @endphp
        @foreach (SubjectStage::cases() as $stage)
            @if ($subjectsByStage->has($stage->value))
                <fieldset>
                    <legend class="text-sm font-semibold text-gray-700">{{ $stage->label() }}</legend>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                        @foreach ($subjectsByStage[$stage->value] as $subject)
                            <label class="inline-flex items-center gap-1 text-sm">
                                <input type="checkbox" name="subjects[]" value="{{ $subject->id }}" class="rounded border-gray-300 text-indigo-600"
                                       @checked(in_array($subject->id, $checked, true))>
                                {{ $subject->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
        @endforeach

        <x-input-error :messages="$errors->get('subjects.*')" class="mt-2" />

        <div class="flex items-center gap-4">
            <x-primary-button>保存</x-primary-button>
            @if (session('status') === 'subjects-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gray-600">保存しました</p>
            @endif
        </div>
    </form>
</section>
