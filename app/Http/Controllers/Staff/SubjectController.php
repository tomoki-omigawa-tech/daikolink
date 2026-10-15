<?php

namespace App\Http\Controllers\Staff;

use App\Enums\SubjectStage;
use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(): View
    {
        return view('staff.subjects.index', [
            'stages' => SubjectStage::cases(),
            'subjectsByStage' => Subject::withCount('users')->orderBy('sort_order')->get()
                ->groupBy(fn (Subject $subject) => $subject->stage->value),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::enum(SubjectStage::class)],
            'name' => [
                'required',
                'string',
                'max:30',
                Rule::unique('subjects', 'name')->where('stage', $request->input('stage')),
            ],
        ], [], ['stage' => '区分', 'name' => '科目名']);

        $subject = Subject::create([
            'stage' => $validated['stage'],
            'name' => $validated['name'],
            'is_active' => true,
            'sort_order' => (int) Subject::max('sort_order') + 1,
        ]);

        return redirect()
            ->route('staff.subjects.index')
            ->with('status', "{$subject->stage->label()}の「{$subject->name}」を追加しました");
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:30',
                Rule::unique('subjects', 'name')->where('stage', $subject->stage->value)->ignore($subject),
            ],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['name' => '科目名']);

        $subject->name = $validated['name'];
        $subject->is_active = $request->boolean('is_active');
        $subject->save();

        return redirect()
            ->route('staff.subjects.index')
            ->with('status', "「{$subject->name}」を更新しました".($subject->is_active ? '' : '（非表示）'));
    }
}
