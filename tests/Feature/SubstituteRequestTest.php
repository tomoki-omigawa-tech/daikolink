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
use Carbon\Carbon;
use Database\Seeders\TimeSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubstituteRequestTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-10-20';

    private User $me;

    private Subject $math;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TimeSlotSeeder::class);
        Carbon::setTestNow(self::DAY.' 10:00:00');
        $this->me = User::factory()->create(['name' => '山田 太郎', 'nickname' => 'ひょー']);
        $this->math = Subject::create(['stage' => SubjectStage::Junior, 'name' => '数学']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function shift(int $period, ?User $user = null, int $seat = 1): Shift
    {
        return Shift::factory()->create([
            'user_id' => ($user ?? $this->me)->id,
            'date' => self::DAY,
            'time_slot_id' => TimeSlot::where('pattern', TimetablePattern::Weekday)->where('period', $period)->where('kind', SlotKind::Secondary)->value('id'),
            'seat' => $seat,
            'grade' => Grade::J2,
            'subject_id' => $this->math->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function store(array $data, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->me)->post(route('requests.store'), array_merge([
            'date' => self::DAY,
            'reason' => '大学の試験のため',
            'handover_note' => '教科書p.52〜の続き',
        ], $data));
    }

    public function test_create_page_lists_own_lessons_of_the_day(): void
    {
        $sixth = $this->shift(6);
        $this->shift(7);
        $this->shift(6, User::factory()->create(), 1);

        $this->actingAs($this->me)
            ->get(route('requests.create', ['date' => self::DAY, 'shift' => $sixth->id]))
            ->assertOk()
            ->assertSee('6限')
            ->assertSee('7限')
            ->assertSee('value="'.$sixth->id.'" class="rounded border-gray-300 text-indigo-600"'."\n".'                                   checked', false);
    }

    public function test_teacher_can_request_substitutes_for_several_lessons(): void
    {
        $sixth = $this->shift(6);
        $seventh = $this->shift(7);

        $this->store(['shift_ids' => [$sixth->id, $seventh->id]])
            ->assertRedirect(route('dashboard', ['week' => 0]))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '2 件'));

        $this->assertSame(2, SubstituteRequest::where('status', RequestStatus::Open)->where('requester_id', $this->me->id)->count());
    }

    public function test_cannot_request_for_another_teachers_lesson(): void
    {
        $others = $this->shift(6, User::factory()->create());

        $this->store(['shift_ids' => [$others->id]])->assertSessionHasErrors('shift_ids');
        $this->assertSame(0, SubstituteRequest::count());
    }

    public function test_cannot_request_after_the_lesson_started_or_twice(): void
    {
        $sixth = $this->shift(6);
        $this->store(['shift_ids' => [$sixth->id]]);

        // 2回目は「すでに依頼が出ています」
        $this->store(['shift_ids' => [$sixth->id]])->assertSessionHasErrors('shift_ids');

        // 授業が始まった後
        Carbon::setTestNow(self::DAY.' 20:20:00');
        $seventh = $this->shift(7);
        $this->travelTo(Carbon::parse(self::DAY.' 20:20:00'));
        $this->store(['shift_ids' => [$seventh->id]])->assertSessionHasErrors('shift_ids');

        $this->assertSame(1, SubstituteRequest::count());
    }

    public function test_handover_note_is_required(): void
    {
        $sixth = $this->shift(6);

        $this->store(['shift_ids' => [$sixth->id], 'handover_note' => ''])->assertSessionHasErrors('handover_note');
    }

    public function test_staff_cannot_create_requests(): void
    {
        $this->store(['shift_ids' => [$this->shift(6)->id]], User::factory()->staff()->create())->assertForbidden();
    }

    public function test_open_requests_are_listed_twenty_per_page(): void
    {
        $others = User::factory()->count(25)->create();
        foreach ($others as $teacher) {
            SubstituteRequest::factory()->create(['shift_id' => $this->shift(6, $teacher)->id]);
        }

        $response = $this->actingAs($this->me)->get(route('requests.index'));

        $response->assertOk()->assertSee('中2 数学');
        $this->assertCount(20, $response->viewData('requests'));
    }

    public function test_detail_shows_handover_note_and_requester(): void
    {
        $sixth = $this->shift(6);
        $this->store(['shift_ids' => [$sixth->id]]);

        $this->actingAs(User::factory()->create())
            ->get(route('requests.show', SubstituteRequest::firstOrFail()))
            ->assertOk()
            ->assertSee('教科書p.52〜の続き')
            ->assertSee('ひょー')
            ->assertSee('募集中');
    }
}
