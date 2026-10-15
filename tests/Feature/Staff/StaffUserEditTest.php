<?php

namespace Tests\Feature\Staff;

use App\Enums\SubjectStage;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffUserEditTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff()->create(['name' => '教室 スタッフ']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateUser(User $user, array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->put(route('staff.users.update', $user), array_merge([
            'name' => $user->name,
            'nickname' => $user->nickname,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active ? '1' : '0',
        ], $data));
    }

    public function test_teacher_cannot_edit_accounts(): void
    {
        $teacher = User::factory()->create(['name' => '山田 太郎']);

        $this->actingAs($teacher)->get(route('staff.users.edit', $teacher))->assertForbidden();
    }

    public function test_staff_can_edit_nickname_subjects_and_status_of_a_teacher(): void
    {
        $teacher = User::factory()->create(['name' => '山田 太郎']);
        $math = Subject::create(['stage' => SubjectStage::Junior, 'name' => '数学']);
        $hidden = Subject::create(['stage' => SubjectStage::Other, 'name' => '旧科目', 'is_active' => false]);
        $teacher->subjects()->attach($hidden);

        $this->updateUser($teacher, ['nickname' => 'ひょー', 'is_active' => '0', 'subjects' => [$math->id]])
            ->assertRedirect(route('staff.users.index'));

        $teacher->refresh();
        $this->assertSame('ひょー', $teacher->nickname);
        $this->assertFalse($teacher->is_active);
        $this->assertEqualsCanonicalizing([$math->id, $hidden->id], $teacher->subjects->pluck('id')->all());
    }

    public function test_changing_email_sends_verification_again(): void
    {
        Notification::fake();
        $teacher = User::factory()->create(['name' => '山田 太郎']);

        $this->updateUser($teacher, ['email' => 'new@example.com'])->assertSessionHasNoErrors();

        $this->assertNull($teacher->refresh()->email_verified_at);
        Notification::assertSentTo($teacher, VerifyEmail::class);
    }

    public function test_staff_cannot_deactivate_or_demote_themselves(): void
    {
        $this->updateUser($this->staff, ['is_active' => '0'])->assertSessionHasErrors('role');
        $this->updateUser($this->staff, ['role' => 'teacher'])->assertSessionHasErrors('role');

        $this->assertTrue($this->staff->refresh()->is_active);
    }
}
