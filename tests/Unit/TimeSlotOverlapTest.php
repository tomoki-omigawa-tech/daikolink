<?php

namespace Tests\Unit;

use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use App\Models\TimeSlot;
use Tests\TestCase;

class TimeSlotOverlapTest extends TestCase
{
    private function slot(int $period, SlotKind $kind, string $start, string $end): TimeSlot
    {
        return new TimeSlot([
            'pattern' => TimetablePattern::Weekday,
            'period' => $period,
            'kind' => $kind,
            'name' => "{$period}限",
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    public function test_secondary_overlaps_both_elementary_halves(): void
    {
        $secondary = $this->slot(5, SlotKind::Secondary, '17:15:00', '18:35:00');

        $this->assertTrue($secondary->overlaps($this->slot(5, SlotKind::ElementaryFirst, '17:05:00', '17:50:00')));
        $this->assertTrue($secondary->overlaps($this->slot(5, SlotKind::ElementarySecond, '17:50:00', '18:35:00')));
    }

    public function test_elementary_halves_do_not_overlap_each_other(): void
    {
        $first = $this->slot(5, SlotKind::ElementaryFirst, '17:05:00', '17:50:00');
        $second = $this->slot(5, SlotKind::ElementarySecond, '17:50:00', '18:35:00');

        $this->assertFalse($first->overlaps($second));
    }

    public function test_different_periods_do_not_overlap(): void
    {
        $fifth = $this->slot(5, SlotKind::ElementarySecond, '17:50:00', '18:35:00');
        $sixth = $this->slot(6, SlotKind::Secondary, '18:45:00', '20:05:00');

        $this->assertFalse($fifth->overlaps($sixth));
    }
}
