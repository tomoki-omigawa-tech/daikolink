<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Requests\StoreSubstituteRequestRequest;
use App\Models\Shift;
use App\Models\SubstituteRequest;
use App\Services\SubstituteRequestService;
use App\Support\DateLabel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubstituteRequestController extends Controller
{
    /**
     * 募集中の代講（今日以降、授業の日時順に20件ずつ）
     */
    public function index(): View
    {
        $requests = SubstituteRequest::with(['shift.timeSlot', 'shift.subject', 'requester'])
            ->whereIn('status', RequestStatus::activeValues())
            ->whereHas('shift', fn ($query) => $query->where('date', '>=', today()->toDateString()))
            ->orderBy(Shift::select('date')->whereColumn('shifts.id', 'substitute_requests.shift_id'))
            ->orderBy('id')
            ->paginate(20);

        return view('requests.index', ['requests' => $requests]);
    }

    /**
     * 代講依頼の作成：その日の自分の授業を選ぶ
     */
    public function create(Request $request): View
    {
        abort_unless($request->user()->isTeacher(), 403);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'shift' => ['nullable', 'integer'],
        ]);
        $date = Carbon::parse($validated['date']);

        $shifts = Shift::with(['timeSlot', 'subject', 'activeRequests'])
            ->where('user_id', $request->user()->id)
            ->where('date', $date->toDateString())
            ->get()
            ->sortBy(fn (Shift $shift) => sprintf('%04d', $shift->timeSlot->sort_order).$shift->seat)
            ->values();

        return view('requests.create', [
            'date' => $date,
            'shifts' => $shifts,
            'selected' => (int) ($validated['shift'] ?? 0),
        ]);
    }

    public function store(StoreSubstituteRequestRequest $request, SubstituteRequestService $service): RedirectResponse
    {
        $created = $service->create(
            $request->user(),
            array_map('intval', $request->validated('shift_ids')),
            $request->validated('reason'),
            $request->validated('handover_note'),
        );

        return redirect()
            ->route('dashboard', ['week' => Carbon::parse($request->validated('date'))->greaterThan(today()->endOfWeek()) ? 1 : 0])
            ->with('status', DateLabel::date(Carbon::parse($request->validated('date')))."の代講依頼を出しました（{$created->count()} 件）");
    }

    public function show(SubstituteRequest $substituteRequest): View
    {
        $substituteRequest->load(['shift.timeSlot', 'shift.subject', 'requester']);

        return view('requests.show', ['substituteRequest' => $substituteRequest]);
    }
}
