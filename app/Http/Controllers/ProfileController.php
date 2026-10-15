<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->load('subjects'),
            'subjectsByStage' => Subject::active()->orderBy('sort_order')->get()
                ->groupBy(fn (Subject $subject) => $subject->stage->value),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'nickname', 'email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // 新しい画像を入れたとき・削除を選んだときは、古い画像を消す
        if ($request->hasFile('avatar') || $request->boolean('remove_avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->hasFile('avatar')
                ? $request->file('avatar')->store('avatars', 'public')
                : null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * 講師本人が担当できる科目を更新する（非表示にした科目のチェックはそのまま残す）
     */
    public function updateSubjects(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isTeacher(), 403);

        $validated = $request->validate([
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer', Rule::exists('subjects', 'id')->where('is_active', true)],
        ]);

        $hidden = $user->subjects()->where('is_active', false)->pluck('subjects.id');
        $user->subjects()->sync(collect($validated['subjects'] ?? [])->map(fn ($id) => (int) $id)->merge($hidden)->unique()->all());

        return Redirect::route('profile.edit')->with('status', 'subjects-updated');
    }
}
