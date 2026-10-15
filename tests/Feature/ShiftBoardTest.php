<?php

namespace Tests\Feature;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\SubstituteRequest;
use App\Models\TimeSlot;
use App\Models\User;
use Database\Seeders\TimeSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftBoardTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-10-20';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TimeSlotSeeder::class);
    }

    private function shift(User $teacher, int $period, int $seat, SlotKind $kind = SlotKind::Secondary): Shift
    {
        return Shift::factory()->create([
            'user_id' => $teacher->id,
            'date' => self::DAY,
            'time_slot_id' => TimeSlot::where('pattern', TimetablePattern::Weekday)->where('period', $period)->where('kind', $kind)->value('id'),
            'seat' => $seat,
            'grade' => $kind === SlotKind::Secondary ? Grade::J2 : Grade::E5,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sixthPeriod(User $viewer): array
    {
        $availability = $this->actingAs($viewer)->get(route('shifts.board', ['date' => self::DAY]))->viewData('availability');

        return collect($availability)->firstWhere('period', 6);
    }

    public function test_free_and_half_free_teachers_are_listed_for_each_period(): void
    {
        $full = User::factory()->create(['name' => '満席 先生']);
        $half = User::factory()->create(['name' => '片方 先生']);
        $free = User::factory()->create(['name' => '空き 先生']);
        User::factory()->inactive()->create(['name' => '退職 先生']);
        $this->shift($full, 6, 1);
        $this->shift($full, 6, 2);
        $this->shift($half, 6, 1);

        $row = $this->sixthPeriod($free);

        $this->assertEqualsCanonicalizing([$free->id], $row['free']->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$half->id], $row['half']->pluck('id')->all());
    }

    public function test_elementary_halves_count_as_one_seat_each(): void
    {
        // 5限：左の席に小学生①②、右の席は空き → 席が片方空いている
        $teacher = User::factory()->create(['name' => '小学 先生']);
        foreach ([SlotKind::ElementaryFirst, SlotKind::ElementarySecond] as $kind) {
            Shift::factory()->create([
                'user_id' => $teacher->id,
                'date' => self::DAY,
                'time_slot_id' => TimeSlot::where('pattern', TimetablePattern::Weekday)->where('period', 5)->where('kind', $kind)->value('id'),
                'seat' => 1,
                'grade' => Grade::E5,
            ]);
        }

        $availability = $this->actingAs($teacher)->get(route('shifts.board', ['date' => self::DAY]))->viewData('availability');
        $fifth = collect($availability)->firstWhere('period', 5);

        $this->assertTrue($fifth['half']->contains('id', $teacher->id));
    }

    public function test_board_shows_lessons_and_requested_ones(): void
    {
        $teacher = User::factory()->create(['name' => '山田 太郎', 'nickname' => 'ひょー']);
        $shift = $this->shift($teacher, 6, 1);
        $request = SubstituteRequest::factory()->create(['shift_id' => $shift->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('shifts.board', ['date' => self::DAY]))
            ->assertOk()
            ->assertSee('ひょー')
            ->assertSee('中2')
            ->assertSee(route('requests.show', $request))
            ->assertSee('この日の空き状況');
    }

    public function test_closed_day_is_shown(): void
    {
        ClosedDay::create(['date' => self::DAY, 'note' => '休講']);

        $this->actingAs(User::factory()->create())
            ->get(route('shifts.board', ['date' => self::DAY]))
            ->assertOk()
            ->assertSee('この日は休みです（休講）');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('shifts.board'))->assertRedirect(route('login'));
    }
}
