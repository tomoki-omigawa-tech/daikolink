<?php

namespace Tests\Feature;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\SubstituteRequest;
use App\Models\Term;
use App\Models\TermLesson;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\TermTimetableService;
use Carbon\Carbon;
use Database\Seeders\TimeSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermTimetableServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $teacher;

    private Term $term;

    private TermTimetableService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TimeSlotSeeder::class);
        $this->staff = User::factory()->staff()->create();
        $this->teacher = User::factory()->create();
        // 10/19（月）〜11/8（日）の3週間。火曜は 10/20・10/27・11/3
        $this->term = Term::create(['fiscal_year' => 2026, 'term_no' => 2, 'start_date' => '2026-10-19', 'end_date' => '2026-11-08']);
        $this->service = app(TermTimetableService::class);
    }

    private function slot(TimetablePattern $pattern, int $period, SlotKind $kind): TimeSlot
    {
        return TimeSlot::where('pattern', $pattern)->where('period', $period)->where('kind', $kind)->firstOrFail();
    }

    private function tuesdayLesson(int $period = 6, Grade $grade = Grade::J2): TermLesson
    {
        return TermLesson::create([
            'term_id' => $this->term->id,
            'weekday' => 2,
            'time_slot_id' => $this->slot(TimetablePattern::Weekday, $period, SlotKind::Secondary)->id,
            'seat' => 1,
            'user_id' => $this->teacher->id,
            'grade' => $grade,
        ]);
    }

    private function apply(string $from): array
    {
        return $this->service->apply($this->term, Carbon::parse($from), $this->staff);
    }

    private function gradeOn(string $date): ?Grade
    {
        return Shift::where('user_id', $this->teacher->id)->where('date', $date)->first()?->grade;
    }

    public function test_apply_creates_shifts_on_the_lesson_weekday_within_the_term(): void
    {
        $lesson = $this->tuesdayLesson();

        // 学期より前の日付を指定しても、学期の開始日から作る
        $result = $this->apply('2026-10-01');

        $this->assertSame(3, $result['created']);
        $this->assertSame(
            ['2026-10-20', '2026-10-27', '2026-11-03'],
            Shift::orderBy('date')->get()->map(fn (Shift $shift) => $shift->date->toDateString())->all()
        );
        $this->assertTrue(Shift::where('term_lesson_id', $lesson->id)->where('is_manual', false)->count() === 3);
    }

    public function test_closed_days_are_skipped(): void
    {
        $this->tuesdayLesson();
        ClosedDay::create(['date' => '2026-10-27', 'note' => '休講']);

        $this->assertSame(2, $this->apply('2026-10-19')['created']);
        $this->assertNull($this->gradeOn('2026-10-27'));
    }

    public function test_reapply_keeps_manual_shifts_and_shifts_with_requests(): void
    {
        $lesson = $this->tuesdayLesson();
        $this->apply('2026-10-19');

        // 10/20 は個別に変更、10/27 は代講依頼が出ている
        $manual = Shift::where('date', '2026-10-20')->firstOrFail();
        $manual->grade = Grade::J3;
        $manual->is_manual = true;
        $manual->save();
        SubstituteRequest::factory()->create(['shift_id' => Shift::where('date', '2026-10-27')->value('id')]);

        $lesson->update(['grade' => Grade::J1]);
        $result = $this->apply('2026-10-19');

        $this->assertSame(['created' => 1, 'deleted' => 1, 'skipped' => 2], $result);
        $this->assertSame(Grade::J3, $this->gradeOn('2026-10-20'));
        $this->assertSame(Grade::J2, $this->gradeOn('2026-10-27'));
        $this->assertSame(Grade::J1, $this->gradeOn('2026-11-03'));
    }

    public function test_apply_from_a_later_date_does_not_touch_earlier_shifts(): void
    {
        $lesson = $this->tuesdayLesson();
        $this->apply('2026-10-19');

        $lesson->update(['grade' => Grade::J1]);
        $this->apply('2026-10-28');

        $this->assertSame(Grade::J2, $this->gradeOn('2026-10-20'));
        $this->assertSame(Grade::J2, $this->gradeOn('2026-10-27'));
        $this->assertSame(Grade::J1, $this->gradeOn('2026-11-03'));
    }

    public function test_intensive_days_are_skipped(): void
    {
        $this->tuesdayLesson();
        $other = User::factory()->create();
        $intensive = Shift::factory()->create([
            'user_id' => $other->id,
            'date' => '2026-10-27',
            'time_slot_id' => $this->slot(TimetablePattern::Intensive, 1, SlotKind::Secondary)->id,
        ]);
        $intensive->is_manual = true;
        $intensive->save();

        $this->assertSame(2, $this->apply('2026-10-19')['created']);
        $this->assertNull($this->gradeOn('2026-10-27'));
    }

    public function test_lesson_is_skipped_when_teacher_has_no_free_seat(): void
    {
        $this->tuesdayLesson(5);
        // 10/20 は同じ時間に小学生集団（満席）を個別に入れている
        $group = Shift::factory()->create([
            'user_id' => $this->teacher->id,
            'date' => '2026-10-20',
            'time_slot_id' => $this->slot(TimetablePattern::Weekday, 5, SlotKind::ElementaryGroup)->id,
            'grade' => null,
        ]);
        $group->is_manual = true;
        $group->save();

        $result = $this->apply('2026-10-19');

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, Shift::where('user_id', $this->teacher->id)->where('date', '2026-10-20')->count());
    }
}
