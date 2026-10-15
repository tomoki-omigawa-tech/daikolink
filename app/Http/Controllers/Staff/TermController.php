<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreTermRequest;
use App\Http\Requests\Staff\UpdateTermCellRequest;
use App\Models\Term;
use App\Models\User;
use App\Services\TermTimetableService;
use App\Support\CellEditorConfig;
use App\Support\DateLabel;
use App\Support\TimetableGrid;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'editorConfig' => CellEditorConfig::make(route('staff.terms.cell', $term), ['weekday' => $weekday], $teachers, $slots, $lessons),
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
}
