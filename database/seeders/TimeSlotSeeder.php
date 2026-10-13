<?php

namespace Database\Seeders;

use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use App\Models\TimeSlot;
use Illuminate\Database\Seeder;

class TimeSlotSeeder extends Seeder
{
    /**
     * 森塾の時間割（平日：5〜7限、土曜：3〜7限、講習：1〜7限・中高生のみ）
     */
    public function run(): void
    {
        $g = SlotKind::ElementaryGroup;
        $s = SlotKind::Secondary;
        $e1 = SlotKind::ElementaryFirst;
        $e2 = SlotKind::ElementarySecond;

        $timetables = [
            TimetablePattern::Weekday->value => [
                [5, $g, '17:15', '18:15'], [5, $s, '17:15', '18:35'], [5, $e1, '17:05', '17:50'], [5, $e2, '17:50', '18:35'],
                [6, $s, '18:45', '20:05'],
                [7, $s, '20:15', '21:35'],
            ],
            TimetablePattern::Saturday->value => [
                [3, $g, '13:20', '14:20'], [3, $s, '13:20', '14:40'], [3, $e1, '13:10', '13:55'], [3, $e2, '13:55', '14:40'],
                [4, $s, '14:50', '16:10'], [4, $e1, '14:50', '15:35'], [4, $e2, '15:35', '16:20'],
                [5, $s, '16:30', '17:50'], [5, $e1, '16:30', '17:10'], [5, $e2, '17:10', '17:50'],
                [6, $s, '18:05', '19:25'],
                [7, $s, '19:35', '20:55'],
            ],
            TimetablePattern::Intensive->value => [
                [1, $s, '09:30', '10:50'],
                [2, $s, '11:00', '12:20'],
                [3, $s, '13:20', '14:40'],
                [4, $s, '14:50', '16:10'],
                [5, $s, '16:30', '17:50'],
                [6, $s, '18:05', '19:25'],
                [7, $s, '19:35', '20:55'],
            ],
        ];

        foreach ($timetables as $pattern => $slots) {
            foreach ($slots as [$period, $kind, $start, $end]) {
                TimeSlot::updateOrCreate(
                    ['pattern' => $pattern, 'period' => $period, 'kind' => $kind->value],
                    [
                        'name' => $this->nameFor($period, $kind),
                        'start_time' => $start,
                        'end_time' => $end,
                        'sort_order' => $period * 10 + $kind->sortWeight(),
                    ]
                );
            }
        }
    }

    /**
     * 出勤簿と同じ表記（例：5限、5限 1/2、5限（小学生集団））
     */
    private function nameFor(int $period, SlotKind $kind): string
    {
        return match ($kind) {
            SlotKind::Secondary => "{$period}限",
            SlotKind::ElementaryFirst => "{$period}限 1/2",
            SlotKind::ElementarySecond => "{$period}限 2/2",
            SlotKind::ElementaryGroup => "{$period}限（小学生集団）",
        };
    }
}
