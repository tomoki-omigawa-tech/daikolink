<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\RequestStatus;
use App\Models\Shift;
use App\Models\SubstituteApplication;
use App\Models\SubstituteRequest;
use App\Models\User;
use App\Notifications\SubstituteConfirmedNotification;
use App\Notifications\SubstituteRequestedNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SubstituteRequestService
{
    /**
     * 自分の授業（シフト）ごとに代講依頼を作る（状態は募集中）。作ったあと、ほかの講師にメールで知らせる
     *
     * @param  list<int>  $shiftIds
     * @return Collection<int, SubstituteRequest>
     */
    public function create(User $requester, array $shiftIds, ?string $reason, string $handoverNote): Collection
    {
        $created = DB::transaction(function () use ($requester, $shiftIds, $reason, $handoverNote) {
            // 同時に2回送られても二重に依頼ができないよう、シフトの行をロックしてから確認する
            $shifts = Shift::with(['timeSlot', 'activeRequests'])
                ->whereIn('id', $shiftIds)
                ->where('user_id', $requester->id)
                ->lockForUpdate()
                ->get();

            if ($shifts->count() !== count(array_unique($shiftIds))) {
                throw ValidationException::withMessages(['shift_ids' => '自分の授業だけ選んでください。']);
            }

            $errors = [];
            foreach ($shifts as $shift) {
                if (! $shift->startsAt()->isFuture()) {
                    $errors[] = "{$shift->timeSlot->name}（{$shift->lessonLabel()}）は授業が始まっているため、依頼できません。";
                }
                if ($shift->activeRequests->isNotEmpty()) {
                    $errors[] = "{$shift->timeSlot->name}（{$shift->lessonLabel()}）は、すでに代講依頼が出ています。";
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages(['shift_ids' => $errors]);
            }

            return $shifts->map(function (Shift $shift) use ($requester, $reason, $handoverNote) {
                $request = new SubstituteRequest([
                    'shift_id' => $shift->id,
                    'requester_id' => $requester->id,
                    'reason' => $reason,
                    'handover_note' => $handoverNote,
                ]);
                $request->status = RequestStatus::Open;
                $request->save();

                return $request;
            })->values()->toBase();
        });

        $recipients = User::teachers()->active()->whereKeyNot($requester->id)->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new SubstituteRequestedNotification(new EloquentCollection($created->all())));
        }

        return $created;
    }

    /**
     * 理由と引き継ぎメモを書き換える
     */
    public function update(SubstituteRequest $request, ?string $reason, string $handoverNote): void
    {
        DB::transaction(function () use ($request, $reason, $handoverNote) {
            $locked = $this->lockActive($request);
            $locked->fill(['reason' => $reason, 'handover_note' => $handoverNote])->save();
        });
    }

    /**
     * 依頼を取り消す（記録は残し、状態を「取消」にする。応募中の人は見送りにする）
     */
    public function cancel(SubstituteRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $locked = $this->lockActive($request);
            $locked->status = RequestStatus::Cancelled;
            $locked->save();
            $this->rejectPending($locked);
        });
    }

    /**
     * 応募する／応募を取り消す（押すたびに切り替わる）
     *
     * @return array{applied: bool, count: int, status: RequestStatus}
     */
    public function toggleApplication(SubstituteRequest $request, User $applicant): array
    {
        return DB::transaction(function () use ($request, $applicant) {
            $locked = $this->lockActive($request);

            $application = SubstituteApplication::where('substitute_request_id', $locked->id)
                ->where('applicant_id', $applicant->id)
                ->lockForUpdate()
                ->first();

            if ($application?->status === ApplicationStatus::Pending) {
                $application->status = ApplicationStatus::Withdrawn;
                $application->save();
                $applied = false;
            } else {
                $this->ensureCanTake($applicant, $locked->shift, '同じ時間に見る生徒が2人を超えるため、応募できません。');

                $application ??= new SubstituteApplication([
                    'substitute_request_id' => $locked->id,
                    'applicant_id' => $applicant->id,
                ]);
                $application->status = ApplicationStatus::Pending;
                $application->save();
                $applied = true;
            }

            $count = $this->syncStatus($locked);

            return ['applied' => $applied, 'count' => $count, 'status' => $locked->status];
        });
    }

    /**
     * 応募者の中から代講者を確定し、シフトの担当者を書き換える。確定したら当事者とスタッフにメールで知らせる
     */
    public function confirm(SubstituteRequest $request, int $applicationId): SubstituteRequest
    {
        $confirmed = DB::transaction(function () use ($request, $applicationId) {
            // 同じ依頼を2人が同時に確定しようとしても、先にロックした方だけが通る
            $locked = $this->lockActive($request);

            $application = SubstituteApplication::with('applicant')
                ->where('substitute_request_id', $locked->id)
                ->whereKey($applicationId)
                ->lockForUpdate()
                ->first();
            if ($application === null || $application->status !== ApplicationStatus::Pending) {
                throw ValidationException::withMessages(['application_id' => 'この応募はすでに取り消されています。']);
            }

            /** @var Shift $shift */
            $shift = Shift::with('timeSlot')->lockForUpdate()->findOrFail($locked->shift_id);
            $substitute = $application->applicant;
            $this->ensureCanTake($substitute, $shift, "{$substitute->displayName()}さんは、同じ時間に見る生徒が2人を超えるため確定できません。");

            // 代講者の同じ授業枠で空いている席に入れる
            $usedSeats = Shift::where('user_id', $substitute->id)
                ->where('date', $shift->date->toDateString())
                ->where('time_slot_id', $shift->time_slot_id)
                ->pluck('seat')
                ->all();
            $seat = collect(range(1, Shift::MAX_SEATS))->first(fn (int $seat) => ! in_array($seat, $usedSeats, true)) ?? 1;

            $shift->user_id = $substitute->id;
            $shift->seat = $seat;
            $shift->is_manual = true;
            $shift->updated_by = $locked->requester_id;
            $shift->save();

            $locked->status = RequestStatus::Confirmed;
            $locked->substitute_id = $substitute->id;
            $locked->confirmed_at = now();
            $locked->save();

            $application->status = ApplicationStatus::Accepted;
            $application->save();
            $this->rejectPending($locked);

            return $locked;
        });

        $confirmed->load(['shift.timeSlot', 'shift.subject', 'requester', 'substitute']);
        $recipients = User::active()
            ->where(fn ($query) => $query
                ->whereIn('id', [$confirmed->requester_id, $confirmed->substitute_id])
                ->orWhere('role', User::ROLE_STAFF))
            ->get();
        Notification::send($recipients, new SubstituteConfirmedNotification($confirmed));

        return $confirmed;
    }

    /**
     * 授業が始まっても代講者が決まらなかった依頼を「不成立」にする
     */
    public function expireStarted(): int
    {
        $targets = SubstituteRequest::with('shift.timeSlot')
            ->whereIn('status', RequestStatus::activeValues())
            ->whereHas('shift', fn ($query) => $query->where('date', '<=', today()->toDateString()))
            ->get()
            ->filter(fn (SubstituteRequest $request) => ! $request->shift->startsAt()->isFuture());

        foreach ($targets as $request) {
            DB::transaction(function () use ($request) {
                $locked = SubstituteRequest::lockForUpdate()->find($request->id);
                if ($locked === null || ! $locked->status->isActive()) {
                    return;
                }
                $locked->status = RequestStatus::Expired;
                $locked->save();
                $this->rejectPending($locked);
            });
        }

        return $targets->count();
    }

    /**
     * 依頼の行をロックし、まだ確定前・授業開始前か確かめる
     */
    private function lockActive(SubstituteRequest $request): SubstituteRequest
    {
        /** @var SubstituteRequest $locked */
        $locked = SubstituteRequest::with('shift.timeSlot')->lockForUpdate()->findOrFail($request->id);

        if (! $locked->status->isActive()) {
            throw ValidationException::withMessages(['request' => "この依頼はすでに「{$locked->status->label()}」になっています。"]);
        }
        if (! $locked->shift->startsAt()->isFuture()) {
            throw ValidationException::withMessages(['request' => 'この授業はすでに始まっています。']);
        }

        return $locked;
    }

    /**
     * その講師がこの授業を引き受けても、同じ時間に見る生徒が2人以内に収まるか
     */
    private function ensureCanTake(User $teacher, Shift $shift, string $message): void
    {
        $entries = Shift::with('timeSlot')
            ->where('user_id', $teacher->id)
            ->where('date', $shift->date->toDateString())
            ->whereKeyNot($shift->id)
            ->get()
            ->toBase()
            ->map(fn (Shift $other) => ['slot' => $other->timeSlot, 'seats' => $other->occupiedSeats()])
            ->push(['slot' => $shift->timeSlot, 'seats' => $shift->occupiedSeats()])
            ->values();

        if (SeatCalculator::peak($entries) > Shift::MAX_SEATS) {
            throw ValidationException::withMessages(['request' => $message]);
        }
    }

    /**
     * 応募中の人数に合わせて「募集中／応募あり」を切り替える
     */
    private function syncStatus(SubstituteRequest $request): int
    {
        $count = $request->applications()->where('status', ApplicationStatus::Pending)->count();
        $request->status = $count > 0 ? RequestStatus::Applied : RequestStatus::Open;
        $request->save();

        return $count;
    }

    private function rejectPending(SubstituteRequest $request): void
    {
        $request->applications()
            ->where('status', ApplicationStatus::Pending)
            ->update(['status' => ApplicationStatus::Rejected]);
    }
}
