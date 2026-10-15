<?php

namespace App\Http\Controllers\Staff;

use App\Enums\TimetablePattern;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateShiftCellRequest;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\DailyShiftService;
use App\Support\CellEditorConfig;
use App\Support\DateLabel;
use App\Support\TimetableGrid;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DailyShiftController extends Controller
{
    public function __construct(private readonly DailyShiftService $service) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'pattern' => ['nullable', Rule::enum(TimetablePattern::class)],
        ]);

        $date = isset($validated['date']) ? Carbon::parse($validated['date']) : today();
        $existingPattern = $this->service->existingPattern($date);
        $pattern = isset($validated['pattern'])
            ? TimetablePattern::from($validated['pattern'])
            : $this->service->patternFor($date);

        $slots = $pattern !== null ? TimeSlot::ofPattern($pattern)->get() : collect();
        $shifts = Shift::with(['timeSlot', 'subject', 'updatedBy'])->where('date', $date->toDateString())->get();
        $visible = $shifts->filter(fn (Shift $shift) => $shift->timeSlot->pattern === $pattern)->values();

        // その日にシフトがある講師を左に寄せる
        $withShifts = $visible->pluck('user_id')->flip();
        $teachers = User::teachers()->active()->with('subjects')->get()
            ->sortBy(fn (User $teacher) => ($withShifts->has($teacher->id) ? '0' : '1').$teacher->displayName())
            ->values();

        return view('staff.shifts.index', [
            'date' => $date,
            'pattern' => $pattern,
            'existingPattern' => $existingPattern,
            'patterns' => TimetablePattern::cases(),
            'closedDay' => ClosedDay::where('date', $date->toDateString())->first(),
            'teachers' => $teachers,
            'grid' => TimetableGrid::build($teachers, $slots, $visible),
            'editorConfig' => $pattern !== null
                ? CellEditorConfig::make(
                    route('staff.shifts.cell'),
                    ['date' => $date->toDateString(), 'pattern' => $pattern->value],
                    $teachers,
                    $slots,
                    $visible,
                )
                : null,
            'lastUpdated' => $shifts->sortByDesc('updated_at')->first(),
        ]);
    }

    public function updateCell(UpdateShiftCellRequest $request): JsonResponse
    {
        $teacher = User::with('subjects')->findOrFail($request->validated('user_id'));

        $warnings = $this->service->saveCell(
            Carbon::parse($request->validated('date')),
            TimetablePattern::from($request->validated('pattern')),
            $teacher,
            (int) $request->validated('period'),
            (int) $request->validated('seat'),
            (string) $request->validated('mode'),
            $request->safe()->only(['secondary', 'first', 'second']),
            $request->user(),
        );

        return response()->json(['ok' => true, 'warnings' => $warnings]);
    }

    public function close(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:50'],
        ]);
        $date = Carbon::parse($validated['date']);

        $deleted = $this->service->close($date, $validated['note'] ?? null);

        return redirect()
            ->route('staff.shifts.index', ['date' => $date->toDateString()])
            ->with('status', DateLabel::date($date)."を休みにしました（シフト {$deleted} 件を削除）");
    }

    public function reopen(Request $request): RedirectResponse
    {
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = Carbon::parse($validated['date']);

        $created = $this->service->reopen($date, $request->user());

        return redirect()
            ->route('staff.shifts.index', ['date' => $date->toDateString()])
            ->with('status', DateLabel::date($date)."の休みを解除しました（時間割からシフトを {$created} 件作成）");
    }

    public function clear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'pattern' => ['nullable', Rule::enum(TimetablePattern::class)],
        ]);
        $date = Carbon::parse($validated['date']);

        $deleted = $this->service->clear($date);

        return redirect()
            ->route('staff.shifts.index', array_filter(['date' => $date->toDateString(), 'pattern' => $validated['pattern'] ?? null]))
            ->with('status', DateLabel::date($date)."のシフトをすべて消しました（{$deleted} 件）");
    }
}
