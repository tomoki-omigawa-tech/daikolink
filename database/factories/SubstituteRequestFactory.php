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
            'student_count' => 2,
            'subject' => '数学・英語',
            'grade' => '中2・中3',
            'reason' => '大学の試験のため',
            'handover_note' => '1人目：教科書p.40〜の続き。2人目：宿題の確認から。',
        ];
    }
}
