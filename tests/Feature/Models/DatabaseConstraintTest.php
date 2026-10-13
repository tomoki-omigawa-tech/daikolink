<?php

namespace Tests\Feature\Models;

use App\Enums\RequestStatus;
use App\Enums\SlotKind;
use App\Models\Shift;
use App\Models\SubstituteApplication;
use App\Models\SubstituteRequest;
use App\Models\TimeSlot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_seat_cannot_be_used_twice(): void
    {
        $shift = Shift::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Shift::factory()->create([
            'user_id' => $shift->user_id,
            'date' => $shift->date,
            'time_slot_id' => $shift->time_slot_id,
            'seat' => $shift->seat,
        ]);
    }

    public function test_teacher_can_have_two_students_in_the_same_slot(): void
    {
        $shift = Shift::factory()->create(['seat' => 1]);

        Shift::factory()->create([
            'user_id' => $shift->user_id,
            'date' => $shift->date,
            'time_slot_id' => $shift->time_slot_id,
            'seat' => 2,
        ]);

        $this->assertSame(2, Shift::where('user_id', $shift->user_id)->count());
    }

    public function test_substitute_request_relations_and_status_cast(): void
    {
        $request = SubstituteRequest::factory()->create()->refresh();

        $this->assertSame(RequestStatus::Open, $request->status);
        $this->assertTrue($request->status->isActive());
        $this->assertTrue($request->requester->is($request->shift->user));
        $this->assertTrue($request->shift->activeRequests->contains($request));
    }

    public function test_same_teacher_cannot_apply_twice_to_same_request(): void
    {
        $application = SubstituteApplication::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        SubstituteApplication::factory()->create([
            'substitute_request_id' => $application->substitute_request_id,
            'applicant_id' => $application->applicant_id,
        ]);
    }

    public function test_group_class_occupies_all_seats(): void
    {
        $group = TimeSlot::factory()->create(['kind' => SlotKind::ElementaryGroup]);
        $shift = Shift::factory()->create(['time_slot_id' => $group->id, 'grade' => null]);

        $this->assertSame(Shift::MAX_SEATS, $shift->occupiedSeats());
        $this->assertSame('小学生集団', $shift->lessonLabel());
    }
}
