<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Shift;
use App\Models\SubstituteRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubstituteRequest>
 */
class SubstituteRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'requester_id' => fn (array $attributes) => Shift::findOrFail($attributes['shift_id'])->user_id,
            'status' => RequestStatus::Open,
            'reason' => '大学の試験のため',
            'handover_note' => '教科書p.40〜の続きから。宿題の確認をお願いします。',
        ];
    }
}
