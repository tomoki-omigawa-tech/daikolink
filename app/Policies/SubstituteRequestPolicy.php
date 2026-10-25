<?php

namespace App\Policies;

use App\Models\SubstituteRequest;
use App\Models\User;

class SubstituteRequestPolicy
{
    /**
     * 依頼の編集（理由・引き継ぎメモ）：依頼した本人だけ、確定前、授業開始前
     */
    public function update(User $user, SubstituteRequest $substituteRequest): bool
    {
        return $this->isRequester($user, $substituteRequest)
            && $this->isStillOpen($substituteRequest);
    }

    /**
     * 依頼の取り消し：編集と同じ条件
     */
    public function delete(User $user, SubstituteRequest $substituteRequest): bool
    {
        return $this->update($user, $substituteRequest);
    }

    /**
     * 応募・応募の取り消し：講師で、自分の依頼ではなく、確定前、授業開始前
     */
    public function apply(User $user, SubstituteRequest $substituteRequest): bool
    {
        return $user->isTeacher()
            && ! $this->isRequester($user, $substituteRequest)
            && $this->isStillOpen($substituteRequest);
    }

    /**
     * 代講者の確定：依頼した本人だけ、確定前、授業開始前
     */
    public function confirm(User $user, SubstituteRequest $substituteRequest): bool
    {
        return $this->update($user, $substituteRequest);
    }

    private function isRequester(User $user, SubstituteRequest $substituteRequest): bool
    {
        return $user->id === $substituteRequest->requester_id;
    }

    /**
     * まだ代講者が決まっておらず、授業も始まっていない
     */
    private function isStillOpen(SubstituteRequest $substituteRequest): bool
    {
        return $substituteRequest->status->isActive()
            && $substituteRequest->shift->startsAt()->isFuture();
    }
}
