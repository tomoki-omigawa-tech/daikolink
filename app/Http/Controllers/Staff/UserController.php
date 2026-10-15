<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreUserRequest;
use App\Http\Requests\Staff\UpdateUserRequest;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->orderByDesc('is_active')
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20);

        return view('staff.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('staff.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'nickname', 'email', 'password']));
        $user->role = $request->validated('role');
        $user->save();
        event(new Registered($user));

        return redirect()
            ->route('staff.users.index')
            ->with('status', "{$user->name} さんのアカウントを発行しました");
    }

    public function edit(User $user): View
    {
        return view('staff.users.edit', [
            'user' => $user->load('subjects'),
            'subjectsByStage' => Subject::active()->orderBy('sort_order')->get()
                ->groupBy(fn (Subject $subject) => $subject->stage->value),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'nickname', 'email']));
        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->role = $request->validated('role');
        $user->is_active = $request->boolean('is_active');
        $user->save();

        // 担当できる科目（非表示にした科目のチェックはそのまま残す）
        if ($user->isTeacher()) {
            $hidden = $user->subjects()->where('is_active', false)->pluck('subjects.id');
            $user->subjects()->sync(
                collect($request->validated('subjects') ?? [])->map(fn ($id) => (int) $id)->merge($hidden)->unique()->all()
            );
        }

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()
            ->route('staff.users.index')
            ->with('status', "{$user->name} さんの情報を更新しました");
    }
}
