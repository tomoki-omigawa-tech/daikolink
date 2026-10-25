<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\Grade;
use App\Enums\RequestStatus;
use App\Enums\SlotKind;
use App\Enums\SubjectStage;
use App\Enums\TimetablePattern;
use App\Models\Shift;
use App\Models\Subject;
use App\Models\SubstituteApplication;
use App\Models\SubstituteRequest;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\SubstituteConfirmedNotification;
use App\Notifications\SubstituteRequestedNotification;
use Carbon\Carbon;
use Database\Seeders\TimeSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Week 23：依頼の編集・取り消し、応募（Ajax）、確定とシフト反映、通知、不成立、一覧の絞り込み
 */
class SubstituteFlowTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-10-20';

    private User $requester;

    private User $teacher;

    private Subject $math;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TimeSlotSeeder::class);
        Carbon::setTestNow(self::DAY.' 10:00:00');
        Notification::fake();
        $this->requester = User::factory()->create(['name' => '山田 太郎', 'nickname' => 'ひょー']);
        $this->teacher = User::factory()->create(['name' => '佐藤 花子', 'nickname' => 'はなちゃん']);
        $this->math = Subject::create(['stage' => SubjectStage::Junior, 'name' => '数学']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function slot(int $period, SlotKind $kind = SlotKind::Secondary): int
    {
        return TimeSlot::where('pattern', TimetablePattern::Weekday)->where('period', $period)->where('kind', $kind)->value('id');
    }

    private function shift(User $user, int $period = 6, int $seat = 1, ?Subject $subject = null, SlotKind $kind = SlotKind::Secondary, ?Grade $grade = Grade::J2): Shift
    {
        return Shift::factory()->create([
            'user_id' => $user->id,
            'date' => self::DAY,
            'time_slot_id' => $this->slot($period, $kind),
            'seat' => $seat,
            'grade' => $grade,
            'subject_id' => ($subject ?? $this->math)->id,
        ]);
    }

    private function openRequest(?Shift $shift = null): SubstituteRequest
    {
        $shift ??= $this->shift($this->requester);

        return SubstituteRequest::factory()->create(['shift_id' => $shift->id, 'requester_id' => $shift->user_id]);
    }

    private function applyAs(User $user, SubstituteRequest $request)
    {
        return $this->actingAs($user)->postJson(route('requests.apply', $request));
    }

    // ---- 編集・取り消し（Update / Delete と Policy） ----

    public function test_requester_can_edit_reason_and_handover_note(): void
    {
        $request = $this->openRequest();

        $this->actingAs($this->requester)->get(route('requests.edit', $request))->assertOk()->assertSee('教科書p.40');
        $this->actingAs($this->requester)
            ->put(route('requests.update', $request), ['reason' => '体調不良', 'handover_note' => 'ワークp.12から'])
            ->assertRedirect(route('requests.show', $request));

        $this->assertSame('ワークp.12から', $request->fresh()->handover_note);
        $this->assertSame('体調不良', $request->fresh()->reason);
    }

    public function test_other_teacher_cannot_edit_or_cancel(): void
    {
        $request = $this->openRequest();

        $this->actingAs($this->teacher)->get(route('requests.edit', $request))->assertForbidden();
        $this->actingAs($this->teacher)->put(route('requests.update', $request), ['handover_note' => 'x'])->assertForbidden();
        $this->actingAs($this->teacher)->delete(route('requests.destroy', $request))->assertForbidden();
        $this->assertSame(RequestStatus::Open, $request->fresh()->status);
    }

    public function test_requester_can_cancel_and_pending_applicants_are_rejected(): void
    {
        $request = $this->openRequest();
        $this->applyAs($this->teacher, $request)->assertOk();

        $this->actingAs($this->requester)->delete(route('requests.destroy', $request))->assertRedirect(route('requests.mine'));

        $this->assertSame(RequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(ApplicationStatus::Rejected, SubstituteApplication::first()->status);
        $this->assertDatabaseHas('substitute_requests', ['id' => $request->id]);
    }

    public function test_cannot_edit_after_lesson_started(): void
    {
        $request = $this->openRequest();
        Carbon::setTestNow(self::DAY.' 18:50:00');

        $this->actingAs($this->requester)->get(route('requests.edit', $request))->assertForbidden();
    }

    // ---- 応募（Ajax） ----

    public function test_apply_toggles_without_reload_and_updates_status(): void
    {
        $request = $this->openRequest();

        $this->applyAs($this->teacher, $request)
            ->assertOk()
            ->assertJson(['applied' => true, 'count' => 1, 'status' => '応募あり']);
        $this->assertSame(RequestStatus::Applied, $request->fresh()->status);

        $this->applyAs($this->teacher, $request)
            ->assertOk()
            ->assertJson(['applied' => false, 'count' => 0, 'status' => '募集中']);
        $this->assertSame(RequestStatus::Open, $request->fresh()->status);
        $this->assertSame(ApplicationStatus::Withdrawn, SubstituteApplication::first()->status);

        // 取り消したあとにもう一度応募できる（重複の行は作らない）
        $this->applyAs($this->teacher, $request)->assertJson(['applied' => true]);
        $this->assertSame(1, SubstituteApplication::count());
    }

    public function test_requester_and_staff_cannot_apply(): void
    {
        $request = $this->openRequest();

        $this->applyAs($this->requester, $request)->assertForbidden();
        $this->applyAs(User::factory()->staff()->create(), $request)->assertForbidden();
    }

    public function test_cannot_apply_when_already_teaching_two_students_at_that_time(): void
    {
        $request = $this->openRequest();
        $this->shift($this->teacher, 6, 1);
        $this->shift($this->teacher, 6, 2);

        $this->applyAs($this->teacher, $request)->assertUnprocessable();
        $this->assertSame(0, SubstituteApplication::count());
    }

    public function test_show_page_has_apply_button_for_others_and_applicants_for_requester(): void
    {
        $request = $this->openRequest();
        $this->applyAs($this->teacher, $request);

        $this->actingAs($this->teacher)->get(route('requests.show', $request))
            ->assertOk()->assertSee('この代講に応募する')->assertDontSee('この先生で確定');
        $this->actingAs($this->requester)->get(route('requests.show', $request))
            ->assertOk()->assertSee('はなちゃん')->assertSee('この先生で確定')->assertDontSee('この代講に応募する');
    }

    // ---- 確定とシフトへの反映 ----

    public function test_requester_confirms_and_shift_moves_to_substitute(): void
    {
        $staff = User::factory()->staff()->create();
        $other = User::factory()->create();
        $shift = $this->shift($this->requester);
        $this->shift($this->teacher, 6, 1); // 代講者は同じ枠に生徒が1人いる → 2席目に入る
        $request = $this->openRequest($shift);
        $this->applyAs($this->teacher, $request);
        $this->applyAs($other, $request);
        $application = SubstituteApplication::where('applicant_id', $this->teacher->id)->first();

        $this->actingAs($this->requester)
            ->post(route('requests.confirm', $request), ['application_id' => $application->id])
            ->assertRedirect(route('requests.show', $request));

        $request->refresh();
        $this->assertSame(RequestStatus::Confirmed, $request->status);
        $this->assertSame($this->teacher->id, $request->substitute_id);
        $this->assertNotNull($request->confirmed_at);

        $shift->refresh();
        $this->assertSame($this->teacher->id, $shift->user_id);
        $this->assertSame(2, $shift->seat);
        $this->assertTrue($shift->is_manual);

        $this->assertSame(ApplicationStatus::Accepted, $application->fresh()->status);
        $this->assertSame(ApplicationStatus::Rejected, SubstituteApplication::where('applicant_id', $other->id)->first()->status);

        Notification::assertSentTo([$this->requester, $this->teacher, $staff], SubstituteConfirmedNotification::class);
        Notification::assertNotSentTo($other, SubstituteConfirmedNotification::class);
    }

    public function test_confirmation_mail_links_to_handover_note_without_its_body(): void
    {
        $request = $this->openRequest();
        $this->applyAs($this->teacher, $request);

        $this->actingAs($this->requester)->post(route('requests.confirm', $request), ['application_id' => SubstituteApplication::first()->id]);

        Notification::assertSentTo($this->teacher, SubstituteConfirmedNotification::class, function ($notification) use ($request) {
            $mail = $notification->toMail($this->teacher);
            $text = implode("\n", $mail->introLines);

            return $mail->actionUrl === route('requests.show', $request)
                && ! str_contains($text, '教科書p.40');
        });
    }

    public function test_only_requester_can_confirm_and_only_once(): void
    {
        $request = $this->openRequest();
        $this->applyAs($this->teacher, $request);
        $applicationId = SubstituteApplication::first()->id;

        $this->actingAs($this->teacher)->post(route('requests.confirm', $request), ['application_id' => $applicationId])->assertForbidden();

        $this->actingAs($this->requester)->post(route('requests.confirm', $request), ['application_id' => $applicationId])->assertRedirect();
        $this->actingAs($this->requester)->post(route('requests.confirm', $request), ['application_id' => $applicationId])->assertForbidden();
    }

    public function test_withdrawn_application_cannot_be_confirmed(): void
    {
        $request = $this->openRequest();
        $this->applyAs($this->teacher, $request);
        $this->applyAs($this->teacher, $request); // 取り消し

        $this->actingAs($this->requester)
            ->from(route('requests.show', $request))
            ->post(route('requests.confirm', $request), ['application_id' => SubstituteApplication::first()->id])
            ->assertSessionHasErrors('application_id');
        $this->assertSame($this->requester->id, $request->shift->fresh()->user_id);
    }

    // ---- 依頼作成時の通知・不成立 ----

    public function test_creating_request_notifies_other_active_teachers(): void
    {
        $shift = $this->shift($this->requester);
        $inactive = User::factory()->inactive()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($this->requester)->post(route('requests.store'), [
            'date' => self::DAY,
            'shift_ids' => [$shift->id],
            'handover_note' => '続きから',
        ])->assertRedirect();

        Notification::assertSentTo($this->teacher, SubstituteRequestedNotification::class);
        Notification::assertNotSentTo([$this->requester, $inactive, $staff], SubstituteRequestedNotification::class);
    }

    public function test_expire_command_marks_started_requests_as_expired(): void
    {
        $started = $this->openRequest($this->shift($this->requester, 6));
        $later = $this->openRequest($this->shift($this->requester, 7));
        $this->applyAs($this->teacher, $started);
        Carbon::setTestNow(self::DAY.' 19:00:00');

        $this->artisan('requests:expire')->assertSuccessful();

        $this->assertSame(RequestStatus::Expired, $started->fresh()->status);
        $this->assertSame(ApplicationStatus::Rejected, SubstituteApplication::first()->status);
        $this->assertSame(RequestStatus::Open, $later->fresh()->status);
    }

    // ---- 一覧（絞り込み・自分の依頼・スタッフ） ----

    public function test_index_can_filter_by_level_and_my_subjects(): void
    {
        $english = Subject::create(['stage' => SubjectStage::Junior, 'name' => '英語']);
        $this->teacher->subjects()->attach($this->math->id);
        $this->openRequest($this->shift($this->requester, 6, 1, $this->math));
        $this->openRequest($this->shift($this->requester, 7, 1, $english, grade: Grade::J3));
        $elementarySubject = Subject::create(['stage' => SubjectStage::Elementary, 'name' => '算数']);
        $this->openRequest($this->shift($this->requester, 5, 1, $elementarySubject, SlotKind::ElementaryFirst, Grade::E5));

        $this->actingAs($this->teacher)->get(route('requests.index'))
            ->assertOk()->assertSee('中2 数学')->assertSee('中3 英語')->assertSee('小5 算数');
        $this->actingAs($this->teacher)->get(route('requests.index', ['mine' => 1]))
            ->assertSee('中2 数学')->assertDontSee('中3 英語')->assertDontSee('小5 算数');
        $this->actingAs($this->teacher)->get(route('requests.index', ['level' => 'elementary']))
            ->assertSee('小5 算数')->assertDontSee('中2 数学');
        $this->actingAs($this->teacher)->get(route('requests.index', ['date' => '2026-10-21']))
            ->assertSee('条件に合う代講はありません');
    }

    public function test_mine_page_lists_my_requests_and_applications(): void
    {
        $mine = $this->openRequest();
        $others = $this->openRequest($this->shift($this->teacher, 7));
        $this->applyAs($this->requester, $others);

        $this->actingAs($this->requester)->get(route('requests.mine'))
            ->assertOk()
            ->assertSee(route('requests.show', $mine))
            ->assertSee(route('requests.show', $others))
            ->assertSee('応募中');
    }

    public function test_staff_can_see_request_status_list(): void
    {
        $this->openRequest();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('staff.requests.index'))->assertOk()->assertSee('ひょー')->assertSee('募集中');
        $this->actingAs($this->teacher)->get(route('staff.requests.index'))->assertForbidden();
    }
}
