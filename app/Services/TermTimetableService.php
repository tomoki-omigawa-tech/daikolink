<?php

namespace App\Services;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\Term;
use App\Models\TermLesson;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TermTimetableService
{
    /**
     * 学期の時間割を、指定日から学期末までのシフトに反映する
     *
     * - 日曜・休みの日・講習の日（講習の授業枠のシフトがある日）は対象外
     * - 時間割から作ったシフトは作り直す。個別に変更したシフトと代講依頼があるシフトは残す
     * - 残したシフトと同じ席、または同じ時刻の生徒が2人を超える授業は作らない（スキップ）
     *
     * @return array{created: int, deleted: int, skipped: int}
     */
    public function apply(Term $term, CarbonInterface $from, User $editor, ?CarbonInterface $until = null): array
    {
        $start = $from->greaterThan($term->start_date) ? Carbon::parse($from->toDateString()) : $term->start_date->copy();
        $result = ['created' => 0, 'deleted' => 0, 'skipped' => 0];
        $end = $until !== null && $until->lessThan($term->end_date) ? Carbon::parse($until->toDateString()) : $term->end_date->copy();

        if ($start->greaterThan($end)) {
            return $result;
        }

        $lessonsByWeekday = $term->lessons()->with('timeSlot')->get()->groupBy('weekday');
        $closed = ClosedDay::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->mapWithKeys(fn (ClosedDay $day) => [$day->date->toDateString() => true]);

        DB::transaction(function () use ($start, $end, $lessonsByWeekday, $closed, $editor, &$result) {
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $day = $date->toDateString();
                if ($date->isSunday() || $closed->has($day)) {
                    continue;
                }

                $dayShifts = Shift::with(['timeSlot', 'substituteRequests'])
                    ->where('date', $day)
                    ->lockForUpdate()
                    ->get();

                if ($dayShifts->contains(fn (Shift $shift) => $shift->timeSlot->pattern === TimetablePattern::Intensive)) {
                    continue;
                }

                [$removable, $kept] = $dayShifts->partition(
                    fn (Shift $shift) => ! $shift->is_manual && $shift->substituteRequests->isEmpty()
                );

                if ($removable->isNotEmpty()) {
                    Shift::whereIn('id', $removable->modelKeys())->delete();
                    $result['deleted'] += $removable->count();
                }

                $taken = [];
                $loads = [];
                foreach ($kept as $shift) {
                    $taken["{$shift->user_id}|{$shift->time_slot_id}|{$shift->seat}"] = true;
                    $loads[$shift->user_id][] = ['slot' => $shift->timeSlot, 'seats' => $shift->occupiedSeats()];
                }

                $rows = [];
                $now = now();

                /** @var TermLesson $lesson */
                foreach ($lessonsByWeekday->get($date->dayOfWeekIso, collect()) as $lesson) {
                    $key = "{$lesson->user_id}|{$lesson->time_slot_id}|{$lesson->seat}";
                    if (isset($taken[$key])) {
                        $result['skipped']++;

                        continue;
                    }

                    $entries = collect($loads[$lesson->user_id] ?? [])
                        ->push(['slot' => $lesson->timeSlot, 'seats' => $lesson->occupiedSeats()]);
                    if (SeatCalculator::peak($entries) > Shift::MAX_SEATS) {
                        $result['skipped']++;

                        continue;
                    }

                    $taken[$key] = true;
                    $loads[$lesson->user_id] = $entries->all();
                    $rows[] = [
                        'user_id' => $lesson->user_id,
                        'date' => $day,
                        'time_slot_id' => $lesson->time_slot_id,
                        'seat' => $lesson->seat,
                        'grade' => $lesson->grade?->value,
                        'subject_id' => $lesson->subject_id,
                        'term_lesson_id' => $lesson->id,
                        'is_manual' => false,
                        'updated_by' => $editor->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    Shift::insert($chunk);
                }
                $result['created'] += count($rows);
            }
        });

        return $result;
    }

    /**
     * 時間割の1マス（講師×曜日×限×席）を保存する
     *
     * mode：empty（空にする）／secondary（中高生）／elementary（小学生 1/2・2/2）／group（小学生集団：両方の席）
     *
     * @param  array<string, mixed>  $data  secondary / first / second ごとに grade・subject_id
     * @return list<string> 警告（担当できる科目に入っていない、など）
     */
    public function saveCell(Term $term, int $weekday, User $teacher, int $period, int $seat, string $mode, array $data): array
    {
        $pattern = $weekday === 6 ? TimetablePattern::Saturday : TimetablePattern::Weekday;
        $slots = TimeSlot::ofPattern($pattern)->where('period', $period)->get()
            ->keyBy(fn (TimeSlot $slot) => $slot->kind->value);

        if ($slots->isEmpty()) {
            throw ValidationException::withMessages(['cell' => 'この曜日にない限です。']);
        }

        $new = CellInput::parse($slots, $seat, $mode, $data);

        DB::transaction(function () use ($term, $weekday, $teacher, $slots, $seat, $mode, $new) {
            $groupSlotId = $slots->get(SlotKind::ElementaryGroup->value)?->id;

            // 今の席の授業と、同じ限の小学生集団を消す（集団にするときはその限の授業をすべて消す）
            $query = TermLesson::where('term_id', $term->id)
                ->where('weekday', $weekday)
                ->where('user_id', $teacher->id)
                ->whereIn('time_slot_id', $slots->pluck('id'));
            if ($mode !== 'group') {
                $query->where(function ($q) use ($seat, $groupSlotId) {
                    $q->where('seat', $seat);
                    if ($groupSlotId !== null) {
                        $q->orWhere('time_slot_id', $groupSlotId);
                    }
                });
            }
            $query->delete();

            $others = TermLesson::with('timeSlot')
                ->where('term_id', $term->id)
                ->where('weekday', $weekday)
                ->where('user_id', $teacher->id)
                ->get();
            $entries = $others->toBase()
                ->map(fn (TermLesson $lesson) => ['slot' => $lesson->timeSlot, 'seats' => $lesson->occupiedSeats()])
                ->concat(array_map(fn (array $item) => [
                    'slot' => $item[0],
                    'seats' => $item[0]->kind->isGroup() ? Shift::MAX_SEATS : 1,
                ], $new))
                ->values();
            if (SeatCalculator::peak($entries) > Shift::MAX_SEATS) {
                throw ValidationException::withMessages(['cell' => '同じ時間に見る生徒が2人を超えます。']);
            }

            foreach ($new as [$slot, $lessonSeat, $grade, $subject]) {
                TermLesson::create([
                    'term_id' => $term->id,
                    'weekday' => $weekday,
                    'time_slot_id' => $slot->id,
                    'seat' => $lessonSeat,
                    'user_id' => $teacher->id,
                    'grade' => $grade,
                    'subject_id' => $subject?->id,
                ]);
            }
        });

        return CellInput::warnings($teacher, $new);
    }
}
