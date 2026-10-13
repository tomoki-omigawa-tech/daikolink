<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Enums\SubjectStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreTermRequest;
use App\Http\Requests\Staff\UpdateTermCellRequest;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermLesson;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\TermTimetableService;
use App\Support\DateLabel;
use App\Support\TimetableGrid;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TermController extends Controller
{
    public function index(): View
    {
        return view('staff.terms.index', [
            'terms' => Term::withCount('lessons')->orderByDesc('start_date')->paginate(10),
        ]);
    }

    public function store(StoreTermRequest $request): RedirectResponse
    {
        $term = Term::create($request->validated());

        return redirect()
            ->route('staff.terms.timetable', $term)
            ->with('status', "{$term->label()}を作成しました");
    }

    public function timetable(Request $request, Term $term): View
    {
        $validated = $request->validate(['weekday' => ['nullable', 'integer', 'between:1,6']]);
        $weekday = (int) ($validated['weekday'] ?? (today()->isSunday() ? 1 : today()->dayOfWeekIso));

        ['slots' => $slots, 'lessons' => $lessons, 'teachers' => $teachers, 'grid' => $grid] = TimetableGrid::forTermWeekday($term, $weekday);

        return view('staff.terms.timetable', [
            'term' => $term,
            'weekday' => $weekday,
            'teachers' => $teachers,
            'grid' => $grid,
            'editorConfig' => $this->editorConfig($term, $weekday, $teachers, $slots, $lessons),
        ]);
    }

    public function updateCell(UpdateTermCellRequest $request, Term $term, TermTimetableService $service): JsonResponse
    {
        $teacher = User::with('subjects')->findOrFail($request->validated('user_id'));

        $warnings = $service->saveCell(
            $term,
            (int) $request->validated('weekday'),
            $teacher,
            (int) $request->validated('period'),
            (int) $request->validated('seat'),
            (string) $request->validated('mode'),
            $request->safe()->only(['secondary', 'first', 'second']),
        );

        return response()->json(['ok' => true, 'warnings' => $warnings]);
    }

    public function apply(Request $request, Term $term, TermTimetableService $service): RedirectResponse
    {
        $validated = $request->validate(['from' => ['required', 'date_format:Y-m-d']]);
        $from = Carbon::parse($validated['from']);

        $result = $service->apply($term, $from, $request->user());

        return redirect()->back()->with(
            'status',
            DateLabel::date($from)."からシフトに反映しました（作成 {$result['created']} 件・作り直しのため削除 {$result['deleted']} 件・スキップ {$result['skipped']} 件）"
        );
    }

    /**
     * 入力枠のための情報（学年・科目・講師の担当科目・限ごとの授業の種類・今の入力内容）
     *
     * @param  Collection<int, User>  $teachers
     * @param  Collection<int, TimeSlot>  $slots
     * @param  Collection<int, TermLesson>  $lessons
     * @return array<string, mixed>
     */
    private function editorConfig(Term $term, int $weekday, Collection $teachers, Collection $slots, Collection $lessons): array
    {
        $state = [];
        foreach ($lessons as $lesson) {
            $period = $lesson->timeSlot->period;
            $kind = $lesson->timeSlot->kind;
            $value = ['grade' => $lesson->grade->value ?? '', 'subject_id' => (string) ($lesson->subject_id ?? '')];

            if ($kind->isGroup()) {
                $state["{$lesson->user_id}-{$period}-1"]['mode'] = 'group';
                $state["{$lesson->user_id}-{$period}-2"]['mode'] = 'group';

                continue;
            }

            $key = "{$lesson->user_id}-{$period}-{$lesson->seat}";
            if ($kind === SlotKind::Secondary) {
                $state[$key]['mode'] = 'secondary';
                $state[$key]['secondary'] = $value;
            } else {
                $state[$key]['mode'] = 'elementary';
                $state[$key][$kind === SlotKind::ElementaryFirst ? 'first' : 'second'] = $value;
            }
        }

        return [
            'updateUrl' => route('staff.terms.cell', $term),
            'weekday' => $weekday,
            'grades' => array_map(fn (Grade $grade) => [
                'value' => $grade->value,
                'label' => $grade->label(),
                'elementary' => $grade->isElementary(),
                'stages' => array_map(fn (SubjectStage $stage) => $stage->value, $grade->subjectStages()),
            ], Grade::cases()),
            'subjects' => Subject::active()->orderBy('sort_order')->get()
                ->map(fn (Subject $subject) => ['id' => (string) $subject->id, 'stage' => $subject->stage->value, 'name' => $subject->name])
                ->values(),
            'teachers' => $teachers->mapWithKeys(fn (User $teacher) => [$teacher->id => [
                'name' => $teacher->displayName(),
                'subjects' => $teacher->subjects->pluck('id')->map(fn ($id) => (string) $id)->values(),
            ]]),
            'periods' => $slots->groupBy('period')->map(fn (Collection $periodSlots) => [
                'hasElementary' => $periodSlots->contains(fn (TimeSlot $slot) => $slot->kind === SlotKind::ElementaryFirst),
                'hasGroup' => $periodSlots->contains(fn (TimeSlot $slot) => $slot->kind->isGroup()),
            ]),
            'state' => (object) $state,
        ];
    }
}
