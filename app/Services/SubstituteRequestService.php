<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\Shift;
use App\Models\SubstituteRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubstituteRequestService
{
    /**
     * 自分の授業（シフト）ごとに代講依頼を作る（状態は募集中）
     *
     * @param  list<int>  $shiftIds
     * @return Collection<int, SubstituteRequest>
     */
    public function create(User $requester, array $shiftIds, ?string $reason, string $handoverNote): Collection
    {
        return DB::transaction(function () use ($requester, $shiftIds, $reason, $handoverNote) {
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
    }
}
