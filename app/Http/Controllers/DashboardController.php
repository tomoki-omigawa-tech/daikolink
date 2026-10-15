<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\SubstituteRequest;
use App\Support\MyWeekGrid;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * 講師：今週・来週の自分のシフト（1週間の表）／スタッフ：今日の状況と各管理画面
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->isStaff()) {
            $today = today()->toDateString();

            return view('dashboard.staff', [
                'todayShifts' => Shift::where('date', $today)->count(),
                'activeRequests' => SubstituteRequest::whereIn('status', RequestStatus::activeValues())->count(),
                'isClosedToday' => ClosedDay::where('date', $today)->exists(),
            ]);
        }

        $validated = $request->validate(['week' => ['nullable', 'integer', 'in:0,1']]);
        $week = (int) ($validated['week'] ?? 0);
        $monday = today()->startOfWeek(CarbonInterface::MONDAY)->addWeeks($week);

        return view('dashboard.teacher', ['week' => $week] + MyWeekGrid::build($user, $monday));
    }
}
