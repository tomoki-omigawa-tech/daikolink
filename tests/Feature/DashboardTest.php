<?php

namespace Tests\Feature;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Enums\SubjectStage;
use App\Enums\TimetablePattern;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\Subject;
use App\Models\SubstituteRequest;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TimeSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private Subject $math;

    private Subject $english;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TimeSlotSeeder::class);
        // 2026-10-20（火）を「今日」として固定する（今週は 10/19〜10/24、来週は 10/26〜10/31）
        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->me = User::factory()->create(['name' => '山田 太郎']);
        $this->math = Subject::create(['stage' => SubjectStage::Junior, 'name' => '数学']);
        $this->english = Subject::create(['stage' => SubjectStage::Junior, 'name' => '英語']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function shift(User $user, string $date, Grade $grade, Subject $subject): Shift
    {
        return Shift::factory()->create([
            'user_id' => $user->id,
            'date' => $date,
            'time_slot_id' => TimeSlot::where('pattern', TimetablePattern::Weekday)->where('period', 6)->where('kind', SlotKind::Secondary)->value('id'),
            'grade' => $grade,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_teacher_sees_own_shifts_of_this_week(): void
    {
        $today = $this->shift($this->me, '2026-10-20', Grade::J2, $this->math);
        $this->shift($this->me, '2026-10-27', Grade::J3, $this->english);
        $this->shift(User::factory()->create(), '2026-10-21', Grade::H1, $this->english);
        SubstituteRequest::factory()->create(['shift_id' => $today->id]);
        ClosedDay::create(['date' => '2026-10-24', 'note' => '休講']);

        $this->actingAs($this->me)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('10/20（火）')
            ->assertSee('中2 数学')
            ->assertSee('data-request="active"', false)
            ->assertSee('休み（休講）')
            // 来週の分・ほかの先生のシフトは出ない
            ->assertDontSee('中3 英語')
            ->assertDontSee('高1 英語');
    }

    public function test_teacher_can_see_next_week(): void
    {
        $this->shift($this->me, '2026-10-27', Grade::J3, $this->english);

        $this->actingAs($this->me)
            ->get(route('dashboard', ['week' => 1]))
            ->assertOk()
            ->assertSee('10/27（火）')
            ->assertSee('中3 英語');
    }

    public function test_teacher_without_shifts_sees_a_message(): void
    {
        $this->actingAs($this->me)
            ->get(route('dashboard'))
            ->assertSee('この週はシフトがありません');
    }

    public function test_staff_sees_today_summary_and_links(): void
    {
        $shift = $this->shift(User::factory()->create(), '2026-10-20', Grade::J2, $this->math);
        SubstituteRequest::factory()->create(['shift_id' => $shift->id]);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['今日', '1 件', 'まだ決まっていない代講依頼', '1 件'])
            ->assertSee(route('staff.shifts.index'));
    }
}
