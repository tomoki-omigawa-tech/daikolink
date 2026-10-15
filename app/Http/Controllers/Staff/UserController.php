<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreUserRequest;
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
}
