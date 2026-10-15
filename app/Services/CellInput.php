<?php

namespace App\Services;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * 表の1マス（講師×限×席）の入力を、授業の一覧に変換する（時間割とシフト管理で共通）
 */
class CellInput
{
    /**
     * mode：empty（空にする）／secondary（中高生）／elementary（小学生 1/2・2/2）／group（小学生集団：両方の席）
     *
     * @param  Collection<string, TimeSlot>  $slots  その限の授業枠（種類で引ける）
     * @param  array<string, mixed>  $data  secondary / first / second ごとに grade・subject_id
     * @return list<array{0: TimeSlot, 1: int, 2: Grade|null, 3: Subject|null}>
     */
    public static function parse(Collection $slots, int $seat, string $mode, array $data): array
    {
        $errors = [];
        $new = [];

        if ($mode === 'group') {
            $group = $slots->get(SlotKind::ElementaryGroup->value);
            if ($group === null) {
                $errors[] = 'この限には小学生集団がありません。';
            } else {
                $new[] = [$group, 1, null, null];
            }
        } elseif ($mode === 'secondary') {
            $new[] = self::lessonFrom($slots->get(SlotKind::Secondary->value), $seat, (array) ($data['secondary'] ?? []), false, $errors, '中高生');
        } elseif ($mode === 'elementary') {
            foreach (['first' => SlotKind::ElementaryFirst, 'second' => SlotKind::ElementarySecond] as $key => $kind) {
                $values = (array) ($data[$key] ?? []);
                if (($values['grade'] ?? null) === null || $values['grade'] === '') {
                    continue;
                }
                $new[] = self::lessonFrom($slots->get($kind->value), $seat, $values, true, $errors, $kind->label());
            }
            if ($new === [] && $errors === []) {
                $errors[] = '1/2か2/2の学年を選んでください。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['cell' => $errors]);
        }

        return array_values(array_filter($new));
    }

    /**
     * 担当できる科目に入っていない科目の警告
     *
     * @param  list<array{0: TimeSlot, 1: int, 2: Grade|null, 3: Subject|null}>  $lessons
     * @return list<string>
     */
    public static function warnings(User $teacher, array $lessons): array
    {
        $warnings = [];

        foreach ($lessons as [, , , $subject]) {
            if ($subject !== null && ! $teacher->subjects->contains('id', $subject->id)) {
                $warnings[] = "{$subject->stage->label()}の{$subject->name}は、{$teacher->displayName()}の担当できる科目に入っていません。";
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $errors
     * @return array{0: TimeSlot, 1: int, 2: Grade, 3: Subject}|null
     */
    private static function lessonFrom(?TimeSlot $slot, int $seat, array $values, bool $elementary, array &$errors, string $label): ?array
    {
        if ($slot === null) {
            $errors[] = "この限には{$label}の授業がありません。";

            return null;
        }

        $grade = Grade::tryFrom((string) ($values['grade'] ?? ''));
        if ($grade === null) {
            $errors[] = "{$label}の学年を選んでください。";

            return null;
        }
        if ($grade->isElementary() !== $elementary) {
            $errors[] = $elementary ? "{$label}には小学生の学年を選んでください。" : "{$label}には中学生か高校生の学年を選んでください。";

            return null;
        }

        $subject = Subject::active()->find($values['subject_id'] ?? null);
        if (! $subject instanceof Subject || ! in_array($subject->stage, $grade->subjectStages(), true)) {
            $errors[] = "{$label}の科目を、{$grade->label()}で選べる科目から選んでください。";

            return null;
        }

        return [$slot, $seat, $grade, $subject];
    }
}
