<?php

namespace Tests\Feature\Staff;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Enums\SubjectStage;
use App\Enums\TimetablePattern;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\Subject;
use App\Models\SubstituteRequest;
use App\Models\Term;
use App\Models\TermLesson;
use App\Models\TimeSlot;
use App\Models\User;
use Database\Seeders\TimeSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyShiftManagementTest extends TestCase
{
    use RefreshDatabase;

    private const TUESDAY = '2026-10-20';

    private User $staff;

    private User $teacher;

    private Subject $math;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TimeSlotSeeder::class);
        $this->staff = User::factory()->staff()->create();
        $this->teacher = User::factory()->create(['name' => '山田 太郎', 'nickname' => 'ひょー']);
        $this->math = Subject::create(['stage' => SubjectStage::Junior, 'name' => '数学']);
        $this->teacher->subjects()->attach($this->math);
    }

    private function slot(TimetablePattern $pattern, int $period, SlotKind $kind): TimeSlot
    {
        return TimeSlot::where('pattern', $pattern)->where('period', $period)->where('kind', $kind)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveCell(array $data = [])
    {
        return $this->actingAs($this->staff)->putJson(route('staff.shifts.cell'), array_merge([
            'date' => self::TUESDAY,
            'pattern' => 'weekday',
            'user_id' => $this->teacher->id,
            'period' => 6,
            'seat' => 1,
            'mode' => 'secondary',
            'secondary' => ['grade' => 'j2', 'subject_id' => $this->math->id],
        ], $data));
    }

    private function generatedShift(int $seat = 1): Shift
    {
        return Shift::factory()->create([
            'user_id' => $this->teacher->id,
            'date' => self::TUESDAY,
            'time_slot_id' => $this->slot(TimetablePattern::Weekday, 6, SlotKind::Secondary)->id,
            'seat' => $seat,
            'grade' => Grade::J1,
        ]);
    }

    public function test_teacher_cannot_manage_daily_shifts(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.shifts.index'))->assertForbidden();
    }

    public function test_staff_sees_shifts_of_the_day_with_manual_mark(): void
    {
        $shift = $this->generatedShift();
        $shift->is_manual = true;
        $shift->save();

        $this->actingAs($this->staff)
            ->get(route('staff.shifts.index', ['date' => self::TUESDAY]))
            ->assertOk()
            ->assertSee('ひょー')
            ->assertSee('中1')
            ->assertSee('時間割から個別に変更');
    }

    public function test_saved_cell_becomes_a_manual_shift_and_keeps_the_other_seat(): void
    {
        $this->generatedShift(1);
        $other = $this->generatedShift(2);

        $this->saveCell()->assertOk();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $this->teacher->id, 'seat' => 1, 'grade' => 'j2', 'is_manual' => true, 'updated_by' => $this->staff->id,
        ]);
        $this->assertDatabaseHas('shifts', ['id' => $other->id, 'is_manual' => false]);
        $this->assertSame(2, Shift::count());
    }

    public function test_shift_with_substitute_request_cannot_be_changed(): void
    {
        $shift = $this->generatedShift();
        SubstituteRequest::factory()->create(['shift_id' => $shift->id]);

        $this->saveCell()->assertUnprocessable()->assertJsonValidationErrors('cell');
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'grade' => 'j1']);
    }

    public function test_closed_day_removes_shifts_and_blocks_new_ones(): void
    {
        $this->generatedShift();

        $this->actingAs($this->staff)
            ->post(route('staff.shifts.close'), ['date' => self::TUESDAY, 'note' => '休講'])
            ->assertRedirect(route('staff.shifts.index', ['date' => self::TUESDAY]));

        $this->assertSame(0, Shift::count());
        $this->assertDatabaseHas('closed_days', ['date' => self::TUESDAY, 'note' => '休講']);
        $this->saveCell()->assertUnprocessable();
    }

    public function test_reopening_a_closed_day_rebuilds_shifts_from_the_timetable(): void
    {
        $term = Term::create(['fiscal_year' => 2026, 'term_no' => 2, 'start_date' => '2026-10-19', 'end_date' => '2026-11-08']);
        TermLesson::create([
            'term_id' => $term->id,
            'weekday' => 2,
            'time_slot_id' => $this->slot(TimetablePattern::Weekday, 6, SlotKind::Secondary)->id,
            'seat' => 1,
            'user_id' => $this->teacher->id,
            'grade' => Grade::J2,
            'subject_id' => $this->math->id,
        ]);
        ClosedDay::create(['date' => self::TUESDAY]);

        $this->actingAs($this->staff)
            ->delete(route('staff.shifts.reopen'), ['date' => self::TUESDAY])
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '1 件作成'));

        $this->assertDatabaseMissing('closed_days', ['date' => self::TUESDAY]);
        // その日だけ作り直す（翌週の火曜は作らない）
        $this->assertSame(1, Shift::count());
    }

    public function test_switching_timetable_requires_clearing_the_day(): void
    {
        $this->generatedShift();

        $this->saveCell(['pattern' => 'intensive', 'period' => 1])->assertUnprocessable();

        $this->actingAs($this->staff)
            ->post(route('staff.shifts.clear'), ['date' => self::TUESDAY, 'pattern' => 'intensive'])
            ->assertRedirect(route('staff.shifts.index', ['date' => self::TUESDAY, 'pattern' => 'intensive']));

        $this->saveCell(['pattern' => 'intensive', 'period' => 1])->assertOk();
        $this->assertSame(TimetablePattern::Intensive, Shift::firstOrFail()->timeSlot->pattern);
    }

    public function test_sunday_needs_intensive_timetable(): void
    {
        $this->saveCell(['date' => '2026-10-18'])->assertUnprocessable();
        $this->saveCell(['date' => '2026-10-18', 'pattern' => 'intensive', 'period' => 1])->assertOk();
    }
}
