<?php

namespace Tests\Feature\Staff;

use App\Enums\SlotKind;
use App\Enums\SubjectStage;
use App\Enums\TimetablePattern;
use App\Models\Shift;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermLesson;
use App\Models\TimeSlot;
use App\Models\User;
use Database\Seeders\TimeSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermTimetableEditTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $teacher;

    private Term $term;

    private Subject $math;

    private Subject $arithmetic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TimeSlotSeeder::class);
        $this->staff = User::factory()->staff()->create();
        $this->teacher = User::factory()->create(['name' => '山田 太郎', 'nickname' => 'ひょー']);
        $this->term = Term::create(['fiscal_year' => 2026, 'term_no' => 2, 'start_date' => '2026-10-19', 'end_date' => '2026-11-08']);
        $this->math = Subject::create(['stage' => SubjectStage::Junior, 'name' => '数学']);
        $this->arithmetic = Subject::create(['stage' => SubjectStage::Elementary, 'name' => '算数']);
        $this->teacher->subjects()->attach($this->math);
    }

    private function slot(int $period, SlotKind $kind): TimeSlot
    {
        return TimeSlot::where('pattern', TimetablePattern::Weekday)->where('period', $period)->where('kind', $kind)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveCell(array $data, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->putJson(route('staff.terms.cell', $this->term), array_merge([
            'weekday' => 2,
            'user_id' => $this->teacher->id,
            'period' => 5,
            'seat' => 1,
        ], $data));
    }

    public function test_teacher_cannot_edit_timetable(): void
    {
        $this->saveCell(['mode' => 'empty'], User::factory()->create())->assertForbidden();
    }

    public function test_staff_can_save_a_secondary_lesson(): void
    {
        $this->saveCell([
            'mode' => 'secondary',
            'secondary' => ['grade' => 'j2', 'subject_id' => $this->math->id],
        ])->assertOk()->assertJson(['warnings' => []]);

        $this->assertDatabaseHas('term_lessons', [
            'user_id' => $this->teacher->id,
            'time_slot_id' => $this->slot(5, SlotKind::Secondary)->id,
            'seat' => 1,
            'grade' => 'j2',
            'subject_id' => $this->math->id,
        ]);
    }

    public function test_elementary_halves_replace_the_lesson_in_the_seat_and_warn_about_subjects(): void
    {
        $this->saveCell(['mode' => 'secondary', 'secondary' => ['grade' => 'j2', 'subject_id' => $this->math->id]]);

        $this->saveCell([
            'mode' => 'elementary',
            'first' => ['grade' => 'e5', 'subject_id' => $this->arithmetic->id],
            'second' => ['grade' => '', 'subject_id' => ''],
        ])->assertOk()->assertJsonCount(1, 'warnings');

        $this->assertSame(1, TermLesson::count());
        $this->assertDatabaseHas('term_lessons', ['time_slot_id' => $this->slot(5, SlotKind::ElementaryFirst)->id, 'grade' => 'e5']);
    }

    public function test_group_class_uses_both_seats(): void
    {
        foreach ([1, 2] as $seat) {
            $this->saveCell(['seat' => $seat, 'mode' => 'secondary', 'secondary' => ['grade' => 'j2', 'subject_id' => $this->math->id]]);
        }

        $this->saveCell(['mode' => 'group'])->assertOk();

        $this->assertSame(1, TermLesson::count());
        $this->assertDatabaseHas('term_lessons', ['time_slot_id' => $this->slot(5, SlotKind::ElementaryGroup)->id]);
    }

    public function test_grade_must_match_the_kind_of_class(): void
    {
        $this->saveCell([
            'mode' => 'secondary',
            'secondary' => ['grade' => 'e5', 'subject_id' => $this->arithmetic->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('cell');

        $this->assertSame(0, TermLesson::count());
    }

    public function test_subject_must_be_available_for_the_grade(): void
    {
        $this->saveCell([
            'mode' => 'secondary',
            'secondary' => ['grade' => 'j2', 'subject_id' => $this->arithmetic->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('cell');
    }

    public function test_empty_removes_the_lesson(): void
    {
        $this->saveCell(['mode' => 'secondary', 'secondary' => ['grade' => 'j2', 'subject_id' => $this->math->id]]);

        $this->saveCell(['mode' => 'empty'])->assertOk();

        $this->assertSame(0, TermLesson::count());
    }

    public function test_apply_button_creates_shifts(): void
    {
        $this->saveCell(['period' => 6, 'mode' => 'secondary', 'secondary' => ['grade' => 'j2', 'subject_id' => $this->math->id]]);

        $this->actingAs($this->staff)
            ->from(route('staff.terms.timetable', [$this->term, 'weekday' => 2]))
            ->post(route('staff.terms.apply', $this->term), ['from' => '2026-10-19'])
            ->assertRedirect(route('staff.terms.timetable', [$this->term, 'weekday' => 2]))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '作成 3 件'));

        $this->assertSame(3, Shift::count());
    }
}
