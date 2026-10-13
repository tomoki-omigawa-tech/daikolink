<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\TimetablePattern;
use App\Models\Shift;
use App\Models\SubstituteRequest;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_demo_data(): void
    {
        $this->seed();

        $this->assertSame(1, User::where('role', User::ROLE_STAFF)->count());
        $this->assertSame(6, TimeSlot::where('pattern', TimetablePattern::Weekday)->count());
        $this->assertSame(12, TimeSlot::where('pattern', TimetablePattern::Saturday)->count());
        $this->assertSame(7, TimeSlot::where('pattern', TimetablePattern::Intensive)->count());
        $this->assertTrue(User::where('email', 'teacher@example.com')->firstOrFail()->shifts()->exists());
        $this->assertSame(5, SubstituteRequest::where('status', RequestStatus::Open)->count());

        foreach (User::teachers()->with('subjects')->get() as $teacher) {
            $this->assertGreaterThanOrEqual(2, $teacher->subjects->count());
        }
    }

    public function test_every_shift_uses_the_timetable_of_its_day(): void
    {
        $this->seed();

        $shifts = Shift::with('timeSlot')->get();

        $this->assertGreaterThan(0, $shifts->count());
        foreach ($shifts as $shift) {
            $this->assertSame(TimetablePattern::defaultFor($shift->date), $shift->timeSlot->pattern);
        }
    }

    public function test_no_teacher_has_more_than_two_students_at_the_same_time(): void
    {
        $this->seed();

        $groups = Shift::with('timeSlot')->get()
            ->groupBy(fn (Shift $shift) => $shift->user_id.'|'.$shift->date->toDateString());

        foreach ($groups as $dayShifts) {
            // 生徒数が最大になるのは、いずれかの授業の開始時刻
            foreach ($dayShifts as $shift) {
                $load = $dayShifts
                    ->filter(fn (Shift $other) => $other->timeSlot->isInSessionAt($shift->timeSlot->start_time))
                    ->sum(fn (Shift $other) => $other->occupiedSeats());

                $this->assertLessThanOrEqual(Shift::MAX_SEATS, $load);
            }
        }
    }
}
