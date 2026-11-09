<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\SubstituteRequest;
use App\Models\User;
use App\Notifications\SubstituteReminderNotification;
use App\Notifications\UnfilledRequestsStaffNotification;
use App\Notifications\UnfilledRequestsTeacherNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * 決まった時刻に送るお知らせ
 * - 前日の夜：明日の代講者へのリマインド
 * - 当日の正午：まだ代講者が決まっていない依頼を、その日に授業のない先生とスタッフへ一斉に連絡
 */
class SubstituteNoticeService
{
    /**
     * 明日の授業の代講が確定している先生に、リマインドを送る。送った依頼の件数を返す
     */
    public function remindTomorrow(): int
    {
        $requests = SubstituteRequest::with(['shift.timeSlot', 'shift.subject', 'requester', 'substitute'])
            ->where('status', RequestStatus::Confirmed)
            ->whereNotNull('substitute_id')
            ->whereNull('reminded_at')
            ->whereHas('shift', fn ($query) => $query->where('date', today()->addDay()->toDateString()))
            ->get();

        if ($requests->isEmpty()) {
            return 0;
        }

        SubstituteRequest::whereKey($requests->modelKeys())->update(['reminded_at' => now()]);

        // 1人の先生が明日いくつか代講する場合は、1通にまとめる
        foreach ($requests->groupBy('substitute_id') as $ownRequests) {
            /** @var SubstituteRequest $first */
            $first = $ownRequests->first();
            if ($first->substitute === null || ! $first->substitute->is_active) {
                continue;
            }
            $first->substitute->notify(new SubstituteReminderNotification($this->sorted($ownRequests)));
        }

        return $requests->count();
    }

    /**
     * 今日の授業で、まだ代講者が決まっていない依頼を一斉に知らせる。知らせた依頼の件数を返す
     */
    public function notifyUnfilledToday(): int
    {
        $today = today()->toDateString();

        $requests = SubstituteRequest::with(['shift.timeSlot', 'shift.subject', 'requester'])
            ->whereIn('status', RequestStatus::activeValues())
            ->whereNull('urgent_notified_at')
            ->whereHas('shift', fn ($query) => $query->where('date', $today))
            ->get()
            ->filter(fn (SubstituteRequest $request) => $request->shift->startsAt()->isFuture())
            ->values();

        if ($requests->isEmpty()) {
            return 0;
        }

        SubstituteRequest::whereKey($requests->modelKeys())
            ->update(['urgent_notified_at' => now(), 'staff_notified_at' => now()]);

        $requests = $this->sorted($requests);

        // その日に授業が入っていない先生のうち、急な連絡を受け取る設定の人
        $teachers = User::teachers()->active()
            ->where('accepts_urgent_requests', true)
            ->whereDoesntHave('shifts', fn ($query) => $query->where('date', $today))
            ->get();
        Notification::send($teachers, new UnfilledRequestsTeacherNotification($requests));

        Notification::send(User::staff()->active()->get(), new UnfilledRequestsStaffNotification($requests));

        return $requests->count();
    }

    /**
     * 授業の開始時刻の順に並べる
     *
     * @param  Collection<int, SubstituteRequest>  $requests
     * @return EloquentCollection<int, SubstituteRequest>
     */
    private function sorted(Collection $requests): EloquentCollection
    {
        return new EloquentCollection(
            $requests->sortBy(fn (SubstituteRequest $request) => $request->shift->startsAt()->timestamp)->values()->all()
        );
    }
}
