<?php

namespace Tests\Feature\Staff;

use App\Enums\SubjectStage;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff()->create();
    }

    public function test_teacher_cannot_manage_subjects(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.subjects.index'))->assertForbidden();
    }

    public function test_staff_can_add_a_subject_and_same_name_is_allowed_in_another_stage(): void
    {
        Subject::create(['stage' => SubjectStage::Elementary, 'name' => '国語']);

        $this->actingAs($this->staff)
            ->post(route('staff.subjects.store'), ['stage' => 'junior', 'name' => '国語'])
            ->assertRedirect(route('staff.subjects.index'));
        $this->actingAs($this->staff)
            ->post(route('staff.subjects.store'), ['stage' => 'junior', 'name' => '国語'])
            ->assertSessionHasErrors('name');

        $this->assertSame(2, Subject::where('name', '国語')->count());
    }

    public function test_hidden_subject_is_not_offered_on_profile(): void
    {
        $subject = Subject::create(['stage' => SubjectStage::Senior, 'name' => '地学基礎']);

        $this->actingAs($this->staff)
            ->put(route('staff.subjects.update', $subject), ['name' => '地学基礎', 'is_active' => '0'])
            ->assertRedirect(route('staff.subjects.index'));
        $this->assertFalse($subject->refresh()->is_active);

        $this->actingAs(User::factory()->create(['name' => '山田 太郎']))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('地学基礎');
    }
}
