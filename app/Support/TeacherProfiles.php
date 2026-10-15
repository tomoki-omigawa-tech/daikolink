<?php

namespace App\Support;

use App\Enums\SubjectStage;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 講師名をクリックしたときに出すプロフィール（画像・できる科目／できない科目）の情報
 */
class TeacherProfiles
{
    /**
     * @param  Collection<int, User>  $teachers  subjects を読み込み済み
     * @return array<string, mixed>
     */
    public static function make(Collection $teachers): array
    {
        return [
            'stages' => array_map(fn (SubjectStage $stage) => ['value' => $stage->value, 'label' => $stage->label()], SubjectStage::cases()),
            'subjects' => Subject::active()->orderBy('sort_order')->get()
                ->map(fn (Subject $subject) => ['id' => $subject->id, 'stage' => $subject->stage->value, 'name' => $subject->name])
                ->values(),
            'teachers' => $teachers->mapWithKeys(fn (User $teacher) => [$teacher->id => [
                'name' => $teacher->displayName(),
                'surname' => $teacher->nickname ? $teacher->surnameLabel() : '',
                'avatar' => $teacher->avatarUrl(),
                'subjects' => $teacher->subjects->pluck('id')->values(),
            ]]),
        ];
    }
}
