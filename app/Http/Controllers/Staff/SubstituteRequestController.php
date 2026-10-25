<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\SubstituteRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubstituteRequestController extends Controller
{
    /**
     * スタッフ向けの代講依頼の状況一覧（US-16）。状態と日付で絞り込める
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_merge(['active', 'all'], array_column(RequestStatus::cases(), 'value')))],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $status = $filters['status'] ?? 'active';

        $query = SubstituteRequest::with(['shift.timeSlot', 'shift.subject', 'requester', 'substitute'])
            ->withCount(['applications as pending_count' => fn ($q) => $q->where('status', ApplicationStatus::Pending)]);

        if ($status === 'active') {
            $query->whereIn('status', RequestStatus::activeValues());
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['date'])) {
            $query->whereHas('shift', fn ($q) => $q->where('date', $filters['date']));
        } else {
            $query->whereHas('shift', fn ($q) => $q->where('date', '>=', today()->subDays(7)->toDateString()));
        }

        $requests = $query
            ->orderBy(Shift::select('date')->whereColumn('shifts.id', 'substitute_requests.shift_id'))
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        return view('staff.requests.index', [
            'requests' => $requests,
            'status' => $status,
            'date' => $filters['date'] ?? null,
        ]);
    }
}
