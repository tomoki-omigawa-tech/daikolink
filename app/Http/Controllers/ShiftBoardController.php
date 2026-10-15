<?php

namespace App\Http\Controllers;

use App\Enums\TimetablePattern;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\Availability;
use App\Support\TimetableGrid;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftBoardController extends Controller
{
    /**
     * その日の全員のシフト（見るだけ）と、限ごとの空き状況
     */
    public function show(Request $request): View
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = isset($validated['date']) ? Carbon::parse($validated['date']) : today();
        $key = $date->toDateString();

        $closedDay = ClosedDay::where('date', $key)->first();
        $shifts = Shift::with(['timeSlot', 'subject', 'activeRequests'])->where('date', $key)->get();
        $pattern = $shifts->first()?->timeSlot->pattern ?? TimetablePattern::defaultFor($date);
        $slots = $pattern !== null ? TimeSlot::ofPattern($pattern)->get() : collect();

        $allTeachers = User::teachers()->active()->with('subjects')->get()
            ->sortBy(fn (User $teacher) => $teacher->displayName())
            ->values();
        $withShifts = $shifts->pluck('user_id')->flip();
        $teachers = $allTeachers->filter(fn (User $teacher) => $withShifts->has($teacher->id))->values();

        return view('shifts.board', [
            'date' => $date,
            'pattern' => $pattern,
            'closedDay' => $closedDay,
            'teachers' => $teachers,
            'allTeachers' => $allTeachers,
            'grid' => TimetableGrid::build($teachers, $slots, $shifts),
            'availability' => $closedDay !== null || $pattern === null ? [] : Availability::forDay($allTeachers, $slots, $shifts),
        ]);
    }
}
