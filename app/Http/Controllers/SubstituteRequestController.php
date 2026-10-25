<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\RequestStatus;
use App\Enums\SlotKind;
use App\Http\Requests\StoreSubstituteRequestRequest;
use App\Http\Requests\UpdateSubstituteRequestRequest;
use App\Models\Shift;
use App\Models\SubstituteApplication;
use App\Models\SubstituteRequest;
use App\Services\SubstituteRequestService;
use App\Support\DateLabel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubstituteRequestController extends Controller
{
    /**
     * 募集中の代講（今日以降、授業の日時順に20件ずつ）。日付・小学生／中高生・担当できる科目で絞り込める
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'level' => ['nullable', 'in:elementary,secondary'],
            'mine' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        $query = SubstituteRequest::with(['shift.timeSlot', 'shift.subject', 'requester'])
            ->withCount(['applications as pending_count' => fn ($q) => $q->where('status', ApplicationStatus::Pending)])
            ->withExists(['applications as applied_by_me' => fn ($q) => $q->where('applicant_id', $user->id)->where('status', ApplicationStatus::Pending)])
            ->whereIn('status', RequestStatus::activeValues())
            ->whereHas('shift', fn ($q) => $q->where('date', '>=', today()->toDateString()));

        if (! empty($filters['date'])) {
            $query->whereHas('shift', fn ($q) => $q->where('date', $filters['date']));
        }

        if (! empty($filters['level'])) {
            $secondary = $filters['level'] === 'secondary';
            $query->whereHas('shift.timeSlot', fn ($q) => $secondary
                ? $q->where('kind', SlotKind::Secondary)
                : $q->where('kind', '!=', SlotKind::Secondary));
        }

        // 自分が担当できる科目の授業だけ（小学生集団は科目がないので含める）
        if (! empty($filters['mine'])) {
            $subjectIds = $user->subjects()->pluck('subjects.id');
            $query->whereHas('shift', fn ($q) => $q->where(fn ($inner) => $inner
                ->whereIn('subject_id', $subjectIds)
                ->orWhereHas('timeSlot', fn ($slot) => $slot->where('kind', SlotKind::ElementaryGroup))));
        }

        $requests = $query
            ->orderBy(Shift::select('date')->whereColumn('shifts.id', 'substitute_requests.shift_id'))
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('requests.index', ['requests' => $requests, 'filters' => $filters]);
    }

    /**
     * 自分が出した依頼と、自分の応募（US-10）
     */
    public function mine(Request $request): View
    {
        $user = $request->user();
        $since = today()->subDays(30)->toDateString();
        $byLessonDate = fn ($column) => Shift::select('date')->whereColumn('shifts.id', $column);

        $requests = SubstituteRequest::with(['shift.timeSlot', 'shift.subject', 'substitute'])
            ->withCount(['applications as pending_count' => fn ($q) => $q->where('status', ApplicationStatus::Pending)])
            ->where('requester_id', $user->id)
            ->whereHas('shift', fn ($q) => $q->where('date', '>=', $since))
            ->orderByDesc($byLessonDate('substitute_requests.shift_id'))
            ->orderByDesc('id')
            ->get();

        $applications = SubstituteApplication::with(['substituteRequest.shift.timeSlot', 'substituteRequest.shift.subject', 'substituteRequest.requester'])
            ->where('applicant_id', $user->id)
            ->whereHas('substituteRequest.shift', fn ($q) => $q->where('date', '>=', $since))
            ->latest('updated_at')
            ->get();

        return view('requests.mine', ['requests' => $requests, 'applications' => $applications]);
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

    public function show(Request $request, SubstituteRequest $substituteRequest): View
    {
        $substituteRequest->load(['shift.timeSlot', 'shift.subject', 'requester', 'substitute', 'applications.applicant']);
        $user = $request->user();

        return view('requests.show', [
            'substituteRequest' => $substituteRequest,
            'pending' => $substituteRequest->applications->where('status', ApplicationStatus::Pending)->values(),
            'appliedByMe' => $substituteRequest->applications
                ->contains(fn (SubstituteApplication $a) => $a->applicant_id === $user->id && $a->status === ApplicationStatus::Pending),
        ]);
    }

    public function edit(SubstituteRequest $substituteRequest): View
    {
        $this->authorize('update', $substituteRequest);
        $substituteRequest->load(['shift.timeSlot', 'shift.subject']);

        return view('requests.edit', ['substituteRequest' => $substituteRequest]);
    }

    public function update(UpdateSubstituteRequestRequest $request, SubstituteRequest $substituteRequest, SubstituteRequestService $service): RedirectResponse
    {
        $this->authorize('update', $substituteRequest);
        $service->update($substituteRequest, $request->validated('reason'), $request->validated('handover_note'));

        return redirect()->route('requests.show', $substituteRequest)->with('status', '依頼の内容を更新しました。');
    }

    /**
     * 依頼の取り消し（データは消さずに状態を「取消」にする）
     */
    public function destroy(SubstituteRequest $substituteRequest, SubstituteRequestService $service): RedirectResponse
    {
        $this->authorize('delete', $substituteRequest);
        $service->cancel($substituteRequest);

        return redirect()->route('requests.mine')->with('status', '代講依頼を取り消しました。');
    }

    /**
     * 応募／応募の取り消し（ページを再読み込みせずに JSON で返す）
     */
    public function apply(Request $request, SubstituteRequest $substituteRequest, SubstituteRequestService $service): JsonResponse
    {
        $this->authorize('apply', $substituteRequest);
        $result = $service->toggleApplication($substituteRequest, $request->user());

        return response()->json([
            'applied' => $result['applied'],
            'count' => $result['count'],
            'status' => $result['status']->label(),
            'message' => $result['applied'] ? '応募しました。' : '応募を取り消しました。',
        ]);
    }

    /**
     * 応募者の中から代講者を確定する
     */
    public function confirm(Request $request, SubstituteRequest $substituteRequest, SubstituteRequestService $service): RedirectResponse
    {
        $this->authorize('confirm', $substituteRequest);
        $validated = $request->validate(['application_id' => ['required', 'integer']]);
        $confirmed = $service->confirm($substituteRequest, (int) $validated['application_id']);

        return redirect()->route('requests.show', $substituteRequest)
            ->with('status', "{$confirmed->substitute?->displayName()}さんに代講が決まりました。シフトも書き換わりました。");
    }
}
