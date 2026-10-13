<?php

namespace Tests\Feature\Staff;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Enums\SubjectStage;
use App\Enums\TimetablePattern;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermLesson;
use App\Models\TimeSlot;
use App\Models\User;
use Database\Seeders\TimeSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TimeSlotSeeder::class);
        $this->staff = User::factory()->staff()->create();
    }

    private function term(): Term
    {
        return Term::create(['fiscal_year' => 2026, 'term_no' => 2, 'start_date' => '2026-09-01', 'end_date' => '2026-12-20']);
    }

    public function test_teacher_cannot_manage_terms(): void
    {
        $teacher = User::factory()->create();

        $this->actingAs($teacher)->get(route('staff.terms.index'))->assertForbidden();
    }

    public function test_staff_can_create_a_term(): void
    {
        $this->actingAs($this->staff)
            ->post(route('staff.terms.store'), [
                'fiscal_year' => 2026, 'term_no' => 2, 'start_date' => '2026-09-01', 'end_date' => '2026-12-20',
            ])
            ->assertRedirect(route('staff.terms.timetable', Term::firstOrFail()));

        $this->assertDatabaseHas('terms', ['fiscal_year' => 2026, 'term_no' => 2]);
    }

    public function test_overlapping_term_is_rejected(): void
    {
        $this->term();

        $this->actingAs($this->staff)
            ->post(route('staff.terms.store'), [
                'fiscal_year' => 2026, 'term_no' => 3, 'start_date' => '2026-12-01', 'end_date' => '2027-03-20',
            ])
            ->assertSessionHasErrors('start_date');
    }

    public function test_same_year_and_term_is_rejected(): void
    {
        $this->term();

        $this->actingAs($this->staff)
            ->post(route('staff.terms.store'), [
                'fiscal_year' => 2026, 'term_no' => 2, 'start_date' => '2027-09-01', 'end_date' => '2027-12-20',
            ])
            ->assertSessionHasErrors('term_no');
    }

    public function test_timetable_shows_nickname_surname_and_lessons(): void
    {
        $term = $this->term();
        $teacher = User::factory()->create(['name' => '山田 太郎', 'nickname' => 'ひょー']);
        $math = Subject::create(['stage' => SubjectStage::Junior, 'name' => '数学']);
        TermLesson::create([
            'term_id' => $term->id,
            'weekday' => 2,
            'time_slot_id' => TimeSlot::where('pattern', TimetablePattern::Weekday)->where('period', 6)->where('kind', SlotKind::Secondary)->value('id'),
            'seat' => 1,
            'user_id' => $teacher->id,
            'grade' => Grade::J2,
            'subject_id' => $math->id,
        ]);

        $this->actingAs($this->staff)
            ->get(route('staff.terms.timetable', [$term, 'weekday' => 2]))
            ->assertOk()
            ->assertSee('ひょー')
            ->assertSee('山田先生')
            ->assertSee('中2 数学')
            // 担当できる科目に数学が入っていない
            ->assertSee('⚠');
    }

    public function test_saturday_uses_saturday_timetable(): void
    {
        $term = $this->term();
        User::factory()->create();

        $this->actingAs($this->staff)
            ->get(route('staff.terms.timetable', [$term, 'weekday' => 6]))
            ->assertOk()
            ->assertSee('3限')
            ->assertSee('13:10〜14:40');
    }
}
