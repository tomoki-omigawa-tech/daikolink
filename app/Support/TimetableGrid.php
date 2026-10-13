<?php

namespace App\Support;

use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use App\Models\Shift;
use App\Models\Term;
use App\Models\TermLesson;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 「縦＝限、横＝講師（2列）」の表を組み立てる。
 * 小学生の授業がある限は2行（上＝1/2、下＝2/2）にし、中高生のマスは2行分、小学生集団は2列×2行分を使う
 */
class TimetableGrid
{
    /**
     * @param  Collection<int, User>  $teachers  表示順の講師（subjects を読み込み済み）
     * @param  Collection<int, TimeSlot>  $slots  その日の時間割の授業枠
     * @param  Collection<int, TermLesson>|Collection<int, Shift>  $lessons  timeSlot・subject を読み込み済み
     * @return list<array{period: int, label: string, time: string, rows: list<list<array<string, mixed>>>}>
     */
    public static function build(Collection $teachers, Collection $slots, Collection $lessons): array
    {
        $grid = [];

        foreach ($slots->groupBy('period') as $period => $periodSlots) {
            $hasElementary = $periodSlots->contains(fn (TimeSlot $slot) => $slot->kind === SlotKind::ElementaryFirst);
            $rowCount = $hasElementary ? 2 : 1;
            $rows = array_fill(0, $rowCount, []);
            $periodLessons = $lessons->filter(fn (TermLesson|Shift $lesson) => $lesson->timeSlot->period === (int) $period);

            foreach ($teachers as $teacher) {
                $mine = $periodLessons->where('user_id', $teacher->id);
                $find = fn (int $seat, SlotKind $kind) => $mine->first(
                    fn (TermLesson|Shift $lesson) => $lesson->seat === $seat && $lesson->timeSlot->kind === $kind
                );

                $group = $mine->first(fn (TermLesson|Shift $lesson) => $lesson->timeSlot->kind->isGroup());
                if ($group !== null) {
                    $rows[0][] = self::cell((int) $period, $teacher, $group, 'group', 1, $rowCount, 2);

                    continue;
                }

                foreach ([1, 2] as $seat) {
                    $secondary = $find($seat, SlotKind::Secondary);
                    if (! $hasElementary || $secondary !== null) {
                        $rows[0][] = self::cell((int) $period, $teacher, $secondary, $secondary ? 'secondary' : 'empty', $seat, $rowCount, 1);

                        continue;
                    }

                    $first = $find($seat, SlotKind::ElementaryFirst);
                    $second = $find($seat, SlotKind::ElementarySecond);
                    if ($first !== null || $second !== null) {
                        $rows[0][] = self::cell((int) $period, $teacher, $first, 'first', $seat, 1, 1);
                        $rows[1][] = self::cell((int) $period, $teacher, $second, 'second', $seat, 1, 1);
                    } else {
                        $rows[0][] = self::cell((int) $period, $teacher, null, 'empty', $seat, 2, 1);
                    }
                }
            }

            $grid[] = [
                'period' => (int) $period,
                'label' => "{$period}限",
                'time' => substr((string) $periodSlots->min('start_time'), 0, 5).'〜'.substr((string) $periodSlots->max('end_time'), 0, 5),
                'rows' => $rows,
            ];
        }

        return $grid;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cell(int $period, User $teacher, TermLesson|Shift|null $lesson, string $type, int $seat, int $rowspan, int $colspan): array
    {
        $warn = $lesson !== null
            && $lesson->subject_id !== null
            && ! $teacher->subjects->contains('id', $lesson->subject_id);

        return [
            'period' => $period,
            'teacher_id' => $teacher->id,
            'seat' => $seat,
            'type' => $type,
            'lesson' => $lesson,
            'label' => $lesson?->lessonLabel() ?? '',
            'warn' => $warn,
            'rowspan' => $rowspan,
            'colspan' => $colspan,
        ];
    }

    /**
     * 学期のある曜日の時間割を組み立てる（$firstUserId の講師を一番左、次に授業がある講師）
     *
     * @return array{slots: Collection<int, TimeSlot>, lessons: Collection<int, TermLesson>, teachers: Collection<int, User>, grid: list<array{period: int, label: string, time: string, rows: list<list<array<string, mixed>>>}>}
     */
    public static function forTermWeekday(Term $term, int $weekday, ?int $firstUserId = null): array
    {
        $pattern = $weekday === 6 ? TimetablePattern::Saturday : TimetablePattern::Weekday;
        $slots = TimeSlot::ofPattern($pattern)->get();
        $lessons = $term->lessons()->with(['timeSlot', 'subject'])->where('weekday', $weekday)->get();

        $withLessons = $lessons->pluck('user_id')->flip();
        $teachers = User::teachers()->active()->with('subjects')->get()
            ->sortBy(fn (User $teacher) => ($teacher->id === $firstUserId ? '0' : ($withLessons->has($teacher->id) ? '1' : '2')).$teacher->displayName())
            ->values();

        return [
            'slots' => $slots,
            'lessons' => $lessons,
            'teachers' => $teachers,
            'grid' => self::build($teachers, $slots, $lessons),
        ];
    }
}
