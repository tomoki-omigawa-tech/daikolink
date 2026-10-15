<?php

namespace Tests\Feature;

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

class TimetableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TimeSlotSeeder::class);
    }

    public function test_message_is_shown_when_there_is_no_term(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('timetable'))
            ->assertOk()
            ->assertSee('まだ時間割が登録されていません');
    }

    public function test_teacher_sees_the_current_term_with_own_column_first(): void
    {
        $term = Term::create([
            'fiscal_year' => 2026,
            'term_no' => 2,
            'start_date' => today()->subMonth()->toDateString(),
            'end_date' => today()->addMonth()->toDateString(),
        ]);
        $me = User::factory()->create(['name' => '小見川 朋輝', 'nickname' => 'とも']);
        $other = User::factory()->create(['name' => '山田 太郎', 'nickname' => 'ひょー']);
        $math = Subject::create(['stage' => SubjectStage::Junior, 'name' => '数学']);
        TermLesson::create([
            'term_id' => $term->id,
            'weekday' => 2,
            'time_slot_id' => TimeSlot::where('pattern', TimetablePattern::Weekday)->where('period', 6)->where('kind', SlotKind::Secondary)->value('id'),
            'seat' => 1,
            'user_id' => $other->id,
            'grade' => Grade::J2,
            'subject_id' => $math->id,
        ]);

        $this->actingAs($me)
            ->get(route('timetable', ['weekday' => 2]))
            ->assertOk()
            ->assertSee('2026年度 2学期')
            ->assertSee('中2 数学')
            ->assertSeeInOrder(['とも', 'あなた', 'ひょー'])
            // 講師の画面ではマスをクリックして編集できない
            ->assertDontSee('edit($event', false)
            // 講師名をクリックするとプロフィールが開く
            ->assertSee('show-profile', false);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('timetable'))->assertRedirect(route('login'));
    }
}
