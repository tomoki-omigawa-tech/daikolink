<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => '山田 太郎',
            'email' => 'yamada@example.com',
            'role' => 'teacher',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('staff.users.index'))->assertRedirect(route('login'));
    }

    public function test_teacher_cannot_access_account_management(): void
    {
        $teacher = User::factory()->create();

        $this->actingAs($teacher)->get(route('staff.users.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('staff.users.store'), $this->validInput())->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'yamada@example.com']);
    }

    public function test_staff_can_view_paginated_user_list(): void
    {
        $staff = User::factory()->staff()->create();
        User::factory()->count(25)->create();

        $response = $this->actingAs($staff)->get(route('staff.users.index'));

        $response->assertOk();
        $this->assertCount(20, $response->viewData('users'));
    }

    public function test_staff_can_issue_teacher_account(): void
    {
        $staff = User::factory()->staff()->create();

        $response = $this->actingAs($staff)->post(route('staff.users.store'), $this->validInput());

        $response->assertRedirect(route('staff.users.index'));
        $user = User::where('email', 'yamada@example.com')->firstOrFail();
        $this->assertSame('teacher', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_email_must_be_unique(): void
    {
        $staff = User::factory()->staff()->create();
        User::factory()->create(['email' => 'yamada@example.com']);

        $this->actingAs($staff)
            ->post(route('staff.users.store'), $this->validInput())
            ->assertSessionHasErrors('email');
    }

    public function test_role_must_be_teacher_or_staff(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('staff.users.store'), $this->validInput(['role' => 'admin']))
            ->assertSessionHasErrors('role');
    }
}
