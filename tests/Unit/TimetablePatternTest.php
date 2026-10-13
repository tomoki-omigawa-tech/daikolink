<?php

namespace Tests\Unit;

use App\Enums\TimetablePattern;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TimetablePatternTest extends TestCase
{
    public function test_weekday_including_national_holiday_uses_weekday_pattern(): void
    {
        // 2026-10-12（月）はスポーツの日だが、平日の時間割
        $this->assertSame(TimetablePattern::Weekday, TimetablePattern::defaultFor(Carbon::parse('2026-10-12')));
        $this->assertSame(TimetablePattern::Weekday, TimetablePattern::defaultFor(Carbon::parse('2026-10-16')));
    }

    public function test_saturday_uses_saturday_pattern(): void
    {
        $this->assertSame(TimetablePattern::Saturday, TimetablePattern::defaultFor(Carbon::parse('2026-10-17')));
    }

    public function test_sunday_has_no_default_pattern(): void
    {
        $this->assertNull(TimetablePattern::defaultFor(Carbon::parse('2026-10-18')));
    }
}
