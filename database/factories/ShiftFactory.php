<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // TimeSlotFactory は平日の枠なので、日付も平日にそろえる
            'date' => function () {
                $date = today()->addDays(fake()->numberBetween(1, 14));
                while (! $date->isWeekday()) {
                    $date->addDay();
                }

                return $date->toDateString();
            },
            'time_slot_id' => TimeSlot::factory(),
            'student_count' => 2,
        ];
    }
}
