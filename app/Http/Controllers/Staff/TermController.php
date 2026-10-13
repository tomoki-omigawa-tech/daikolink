<?php

namespace App\Http\Controllers\Staff;

use App\Enums\TimetablePattern;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreTermRequest;
use App\Models\Term;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\TimetableGrid;
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

        $pattern = $weekday === 6 ? TimetablePattern::Saturday : TimetablePattern::Weekday;
        $slots = TimeSlot::ofPattern($pattern)->get();
        $lessons = $term->lessons()->with(['timeSlot', 'subject'])->where('weekday', $weekday)->get();

        // その曜日に授業がある講師を左に寄せる
        $withLessons = $lessons->pluck('user_id')->flip();
        $teachers = User::teachers()->active()->with('subjects')->get()
            ->sortBy(fn (User $teacher) => ($withLessons->has($teacher->id) ? '0' : '1').$teacher->displayName())
            ->values();

        return view('staff.terms.timetable', [
            'term' => $term,
            'weekday' => $weekday,
            'teachers' => $teachers,
            'grid' => TimetableGrid::build($teachers, $slots, $lessons),
        ]);
    }
}
