<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\TimetablePattern;
use App\Models\Shift;
use App\Models\Subject;
use App\Models\SubstituteRequest;
use App\Models\Term;
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
        $this->assertSame(25, TimeSlot::count());
        $this->assertSame(22, Subject::count());
        $this->assertTrue(User::where('email', 'teacher@example.com')->firstOrFail()->shifts()->exists());
        $this->assertSame(1, Term::count());
        $this->assertSame(0, Shift::whereNull('term_lesson_id')->count());
        $this->assertSame(5, SubstituteRequest::where('status', RequestStatus::Open)->count());

        foreach (User::teachers()->with('subjects')->get() as $teacher) {
            $this->assertGreaterThanOrEqual(3, $teacher->subjects->count());
        }
    }

    public function test_every_shift_uses_the_timetable_of_its_day(): void
    {
        $this->seed();

        foreach (Shift::with('timeSlot')->get() as $shift) {
            $this->assertSame(TimetablePattern::defaultFor($shift->date), $shift->timeSlot->pattern);
        }
    }

    public function test_grade_and_subject_match_the_slot(): void
    {
        $this->seed();

        foreach (Shift::with(['timeSlot', 'subject'])->get() as $shift) {
            if ($shift->timeSlot->kind->isGroup()) {
                continue;
            }

            $this->assertNotNull($shift->grade);
            $this->assertNotNull($shift->subject);
            // 小学生の枠には小学生、中高生の枠には中高生
            $this->assertSame($shift->timeSlot->kind->isElementary(), $shift->grade->isElementary());
            // 学年で選べる区分の科目
            $this->assertContains($shift->subject->stage, $shift->grade->subjectStages());
        }
    }

    public function test_no_teacher_has_more_than_two_students_at_the_same_time(): void
    {
        $this->seed();

        $groups = Shift::with('timeSlot')->get()
            ->groupBy(fn (Shift $shift) => $shift->user_id.'|'.$shift->date->toDateString());

        foreach ($groups as $dayShifts) {
            foreach ($dayShifts as $shift) {
                $load = $dayShifts
                    ->filter(fn (Shift $other) => $other->timeSlot->isInSessionAt($shift->timeSlot->start_time))
                    ->sum(fn (Shift $other) => $other->occupiedSeats());

                $this->assertLessThanOrEqual(Shift::MAX_SEATS, $load);
            }
        }
    }
}
