<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IssuedAccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_is_sent_when_account_is_issued(): void
    {
        Notification::fake();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('staff.users.store'), [
            'name' => '山田 太郎',
            'email' => 'yamada@example.com',
            'role' => 'teacher',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'yamada@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }
}
