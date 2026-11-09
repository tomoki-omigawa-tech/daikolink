<?php

namespace Tests\Feature;

use App\Enums\Grade;
use App\Enums\RequestStatus;
use App\Enums\SlotKind;
use App\Enums\SubjectStage;
use App\Enums\TimetablePattern;
use App\Models\Shift;
use App\Models\Subject;
use App\Models\SubstituteRequest;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\SubstituteReminderNotification;
use App\Notifications\UnfilledRequestsStaffNotification;
use App\Notifications\UnfilledRequestsTeacherNotification;
use Carbon\Carbon;
use Database\Seeders\TimeSlotSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 前日の夜のリマインドと、当日の正午の「至急」の一斉連絡
 */
class SubstituteNoticeTest extends TestCase
{
    use RefreshDatabase;

    /** 火曜日（平日の時間割） */
    private const TODAY = '2026-10-20';

    private const TOMORROW = '2026-10-21';

    private User $requester;

    private User $substitute;

    private Subject $math;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TimeSlotSeeder::class);
        Notification::fake();
        $this->requester = User::factory()->create(['name' => '山田 太郎', 'nickname' => 'ひょー']);
        $this->substitute = User::factory()->create(['name' => '佐藤 花子', 'nickname' => 'はなちゃん']);
        $this->math = Subject::create(['stage' => SubjectStage::Junior, 'name' => '数学']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function shift(User $user, string $date, int $period = 6): Shift
    {
        return Shift::factory()->create([
            'user_id' => $user->id,
            'date' => $date,
            'time_slot_id' => TimeSlot::where('pattern', TimetablePattern::Weekday)->where('period', $period)
                ->where('kind', SlotKind::Secondary)->value('id'),
            'seat' => 1,
            'grade' => Grade::J2,
            'subject_id' => $this->math->id,
        ]);
    }

    private function request(string $date, RequestStatus $status, int $period = 6, ?User $requester = null): SubstituteRequest
    {
        $shift = $this->shift($requester ?? $this->requester, $date, $period);
        $request = SubstituteRequest::factory()->create(['shift_id' => $shift->id, 'requester_id' => $shift->user_id]);
        $request->status = $status;
        if ($status === RequestStatus::Confirmed) {
            $request->substitute_id = $this->substitute->id;
            $request->confirmed_at = now();
            $shift->user_id = $this->substitute->id;
            $shift->save();
        }
        $request->save();

        return $request;
    }

    // ---- 前日の夜のリマインド ----

    public function test_substitute_gets_reminder_the_night_before(): void
    {
        Carbon::setTestNow(self::TODAY.' 20:00:00');
        $request = $this->request(self::TOMORROW, RequestStatus::Confirmed);

        $this->artisan('requests:remind')->assertSuccessful();

        Notification::assertSentTo($this->substitute, SubstituteReminderNotification::class,
            function (SubstituteReminderNotification $notification) {
                $mail = $notification->toMail($this->substitute);

                // 件名だけで日時が分かる（科目・学年は入れない）
                return $mail->subject === '【明日の代講】10/21（水）6限';
            });
        Notification::assertNotSentTo($this->requester, SubstituteReminderNotification::class);
        $this->assertNotNull($request->refresh()->reminded_at);
    }

    public function test_reminder_is_sent_only_once(): void
    {
        Carbon::setTestNow(self::TODAY.' 20:00:00');
        $this->request(self::TOMORROW, RequestStatus::Confirmed);

        $this->artisan('requests:remind');
        $this->artisan('requests:remind');

        Notification::assertSentToTimes($this->substitute, SubstituteReminderNotification::class, 1);
    }

    public function test_several_substitutes_tomorrow_are_combined_into_one_mail(): void
    {
        Carbon::setTestNow(self::TODAY.' 20:00:00');
        $this->request(self::TOMORROW, RequestStatus::Confirmed, 7);
        $this->request(self::TOMORROW, RequestStatus::Confirmed, 6);

        $this->artisan('requests:remind');

        Notification::assertSentToTimes($this->substitute, SubstituteReminderNotification::class, 1);
        Notification::assertSentTo($this->substitute, SubstituteReminderNotification::class,
            fn (SubstituteReminderNotification $notification) => $notification->toMail($this->substitute)->subject === '【明日の代講】10/21（水）6限 ほか1件');
    }

    public function test_reminder_is_not_sent_for_other_days_or_unconfirmed_requests(): void
    {
        Carbon::setTestNow(self::TODAY.' 20:00:00');
        $this->request(self::TODAY, RequestStatus::Confirmed);
        $this->request('2026-10-22', RequestStatus::Confirmed);
        $this->request(self::TOMORROW, RequestStatus::Open, 6);
        $this->request(self::TOMORROW, RequestStatus::Cancelled, 7);

        $this->artisan('requests:remind');

        Notification::assertNothingSent();
    }

    // ---- 当日の正午の「至急」の一斉連絡 ----

    public function test_unfilled_request_is_sent_to_teachers_without_lessons_today_and_staff(): void
    {
        Carbon::setTestNow(self::TODAY.' 12:00:00');
        $request = $this->request(self::TODAY, RequestStatus::Open);

        $busy = User::factory()->create();
        $this->shift($busy, self::TODAY, 5);
        $free = User::factory()->create();
        $optedOut = User::factory()->create();
        $optedOut->accepts_urgent_requests = false;
        $optedOut->save();
        $retired = User::factory()->inactive()->create();
        $staff = User::factory()->staff()->create();

        $this->artisan('requests:notify-unfilled')->assertSuccessful();

        Notification::assertSentTo([$free, $this->substitute], UnfilledRequestsTeacherNotification::class,
            fn (UnfilledRequestsTeacherNotification $notification) => $notification->toMail($free)->subject === '【至急】本日の代講者を探しています');
        Notification::assertNotSentTo([$busy, $optedOut, $retired, $this->requester, $staff], UnfilledRequestsTeacherNotification::class);
        Notification::assertSentTo($staff, UnfilledRequestsStaffNotification::class);

        $request->refresh();
        $this->assertNotNull($request->urgent_notified_at);
        $this->assertNotNull($request->staff_notified_at);
    }

    public function test_urgent_notice_is_sent_only_once(): void
    {
        Carbon::setTestNow(self::TODAY.' 12:00:00');
        $this->request(self::TODAY, RequestStatus::Applied);

        $this->artisan('requests:notify-unfilled');
        $this->artisan('requests:notify-unfilled');

        Notification::assertSentToTimes($this->substitute, UnfilledRequestsTeacherNotification::class, 1);
    }

    public function test_confirmed_started_or_other_day_requests_are_not_included(): void
    {
        Carbon::setTestNow(self::TODAY.' 12:00:00');
        $this->request(self::TODAY, RequestStatus::Confirmed, 6);
        $this->request(self::TOMORROW, RequestStatus::Open);
        $this->request(self::TODAY, RequestStatus::Cancelled, 7);

        $this->artisan('requests:notify-unfilled');

        Notification::assertNothingSent();
    }

    public function test_notices_are_scheduled_at_noon_and_at_night(): void
    {
        $expressions = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($event) => [trim(strstr((string) $event->command, 'requests:')) => $event->expression]);

        $this->assertSame('0 12 * * *', $expressions['requests:notify-unfilled']);
        $this->assertSame('0 20 * * *', $expressions['requests:remind']);
    }

    // ---- 通知の設定 ----

    public function test_teacher_can_turn_off_urgent_notices(): void
    {
        $this->actingAs($this->substitute)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('急な代講の連絡を受け取る');

        $this->actingAs($this->substitute)
            ->put(route('profile.notifications'), ['accepts_urgent_requests' => '0'])
            ->assertRedirect(route('profile.edit'));
        $this->assertFalse($this->substitute->refresh()->accepts_urgent_requests);

        $this->actingAs($this->substitute)
            ->put(route('profile.notifications'), ['accepts_urgent_requests' => '1']);
        $this->assertTrue($this->substitute->refresh()->accepts_urgent_requests);
    }

    public function test_staff_cannot_change_urgent_notice_setting(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->put(route('profile.notifications'), ['accepts_urgent_requests' => '0'])
            ->assertForbidden();
    }
}
