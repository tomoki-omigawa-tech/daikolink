<?php

namespace Tests\Feature;

use App\Enums\SubjectStage;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileExtrasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateProfile(User $user, array $data = [])
    {
        return $this->actingAs($user)->patch(route('profile.update'), array_merge([
            'name' => $user->name,
            'email' => $user->email,
        ], $data));
    }

    public function test_avatar_can_be_uploaded_and_old_one_is_deleted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => '山田 太郎']);

        $this->updateProfile($user, ['avatar' => UploadedFile::fake()->image('first.jpg')])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));
        $first = $user->refresh()->avatar;
        Storage::disk('public')->assertExists($first);

        $this->updateProfile($user, ['avatar' => UploadedFile::fake()->image('second.png')]);
        $second = $user->refresh()->avatar;

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        $this->assertNotNull($user->avatarUrl());
    }

    public function test_avatar_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => '山田 太郎']);
        $this->updateProfile($user, ['avatar' => UploadedFile::fake()->image('first.jpg')]);
        $path = $user->refresh()->avatar;

        $this->updateProfile($user, ['remove_avatar' => '1']);

        $this->assertNull($user->refresh()->avatar);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_avatar_must_be_an_image_within_2mb(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => '山田 太郎']);

        $this->updateProfile($user, ['avatar' => UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');
        $this->updateProfile($user, ['avatar' => UploadedFile::fake()->image('big.jpg')->size(3000)])
            ->assertSessionHasErrors('avatar');
    }

    public function test_nickname_is_saved_and_name_needs_a_space(): void
    {
        $user = User::factory()->create(['name' => '山田 太郎']);

        $this->updateProfile($user, ['nickname' => 'ひょー'])->assertSessionHasNoErrors();
        $this->assertSame('ひょー', $user->refresh()->displayName());

        $this->updateProfile($user, ['name' => '山田太郎'])->assertSessionHasErrors('name');
    }

    public function test_teacher_can_update_own_subjects_and_hidden_subjects_are_kept(): void
    {
        $teacher = User::factory()->create(['name' => '山田 太郎']);
        $math = Subject::create(['stage' => SubjectStage::Junior, 'name' => '数学']);
        $english = Subject::create(['stage' => SubjectStage::Junior, 'name' => '英語']);
        $hidden = Subject::create(['stage' => SubjectStage::Other, 'name' => '旧科目', 'is_active' => false]);
        $teacher->subjects()->attach([$english->id, $hidden->id]);

        $this->actingAs($teacher)
            ->put(route('profile.subjects'), ['subjects' => [$math->id]])
            ->assertRedirect(route('profile.edit'));

        $this->assertEqualsCanonicalizing([$math->id, $hidden->id], $teacher->refresh()->subjects->pluck('id')->all());
    }

    public function test_staff_cannot_set_subjects_on_own_profile(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->put(route('profile.subjects'), ['subjects' => []])
            ->assertForbidden();
    }
}
