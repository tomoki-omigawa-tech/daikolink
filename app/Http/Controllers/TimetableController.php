<?php

namespace App\Http\Controllers;

use App\Models\Term;
use App\Support\TimetableGrid;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableController extends Controller
{
    /**
     * 今の学期の時間割（見るだけ）。自分の列を一番左に表示する
     */
    public function show(Request $request): View
    {
        $validated = $request->validate(['weekday' => ['nullable', 'integer', 'between:1,6']]);
        $weekday = (int) ($validated['weekday'] ?? (today()->isSunday() ? 1 : today()->dayOfWeekIso));

        $term = Term::containing(today()) ?? Term::orderByDesc('start_date')->first();
        if ($term === null) {
            return view('timetable.show', ['term' => null, 'weekday' => $weekday]);
        }

        $timetable = TimetableGrid::forTermWeekday($term, $weekday, $request->user()?->id);

        return view('timetable.show', [
            'term' => $term,
            'weekday' => $weekday,
            'teachers' => $timetable['teachers'],
            'grid' => $timetable['grid'],
        ]);
    }
}
