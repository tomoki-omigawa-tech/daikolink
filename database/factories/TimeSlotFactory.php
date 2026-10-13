<?php

namespace Database\Factories;

use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use App\Models\TimeSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeSlot>
 */
class TimeSlotFactory extends Factory
{
    /**
     * 既定は平日6限と同じ中高生の枠（限の番号はテスト用にユニークにする）
     */
    public function definition(): array
    {
        $period = fake()->unique()->numberBetween(10, 250);

        return [
            'pattern' => TimetablePattern::Weekday,
            'period' => $period,
            'kind' => SlotKind::Secondary,
            'name' => "{$period}限",
            'start_time' => '18:45:00',
            'end_time' => '20:05:00',
            'sort_order' => $period * 10 + 2,
        ];
    }
}
