<?php

namespace App\Support;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Enums\SubjectStage;
use App\Models\Shift;
use App\Models\Subject;
use App\Models\TermLesson;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 表のマスをクリックしたときの入力枠に渡す情報（時間割とシフト管理で共通）
 */
class CellEditorConfig
{
    /**
     * @param  array<string, mixed>  $extra  保存のときに一緒に送る値（weekday や date・pattern）
     * @param  Collection<int, User>  $teachers
     * @param  Collection<int, TimeSlot>  $slots
     * @param  Collection<int, TermLesson>|Collection<int, Shift>  $lessons
     * @return array<string, mixed>
     */
    public static function make(string $updateUrl, array $extra, Collection $teachers, Collection $slots, Collection $lessons): array
    {
        $state = [];

        foreach ($lessons as $lesson) {
            $period = $lesson->timeSlot->period;
            $kind = $lesson->timeSlot->kind;
            $value = ['grade' => $lesson->grade->value ?? '', 'subject_id' => (string) ($lesson->subject_id ?? '')];

            if ($kind->isGroup()) {
                $state["{$lesson->user_id}-{$period}-1"]['mode'] = 'group';
                $state["{$lesson->user_id}-{$period}-2"]['mode'] = 'group';

                continue;
            }

            $key = "{$lesson->user_id}-{$period}-{$lesson->seat}";
            if ($kind === SlotKind::Secondary) {
                $state[$key]['mode'] = 'secondary';
                $state[$key]['secondary'] = $value;
            } else {
                $state[$key]['mode'] = 'elementary';
                $state[$key][$kind === SlotKind::ElementaryFirst ? 'first' : 'second'] = $value;
            }
        }

        return [
            'updateUrl' => $updateUrl,
            'extra' => $extra,
            'grades' => array_map(fn (Grade $grade) => [
                'value' => $grade->value,
                'label' => $grade->label(),
                'elementary' => $grade->isElementary(),
                'stages' => array_map(fn (SubjectStage $stage) => $stage->value, $grade->subjectStages()),
            ], Grade::cases()),
            'subjects' => Subject::active()->orderBy('sort_order')->get()
                ->map(fn (Subject $subject) => ['id' => (string) $subject->id, 'stage' => $subject->stage->value, 'name' => $subject->name])
                ->values(),
            'teachers' => $teachers->mapWithKeys(fn (User $teacher) => [$teacher->id => [
                'name' => $teacher->displayName(),
                'subjects' => $teacher->subjects->pluck('id')->map(fn ($id) => (string) $id)->values(),
            ]]),
            'periods' => $slots->groupBy('period')->map(fn (Collection $periodSlots) => [
                'hasElementary' => $periodSlots->contains(fn (TimeSlot $slot) => $slot->kind === SlotKind::ElementaryFirst),
                'hasGroup' => $periodSlots->contains(fn (TimeSlot $slot) => $slot->kind->isGroup()),
            ]),
            'state' => (object) $state,
        ];
    }
}
