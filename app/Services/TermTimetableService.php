<?php

namespace App\Services;

use App\Enums\TimetablePattern;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\Term;
use App\Models\TermLesson;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

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
    public function apply(Term $term, CarbonInterface $from, User $editor): array
    {
        $start = $from->greaterThan($term->start_date) ? Carbon::parse($from->toDateString()) : $term->start_date->copy();
        $result = ['created' => 0, 'deleted' => 0, 'skipped' => 0];

        if ($start->greaterThan($term->end_date)) {
            return $result;
        }

        $lessonsByWeekday = $term->lessons()->with('timeSlot')->get()->groupBy('weekday');
        $closed = ClosedDay::whereBetween('date', [$start->toDateString(), $term->end_date->toDateString()])
            ->get()
            ->mapWithKeys(fn (ClosedDay $day) => [$day->date->toDateString() => true]);

        DB::transaction(function () use ($start, $term, $lessonsByWeekday, $closed, $editor, &$result) {
            foreach (CarbonPeriod::create($start, $term->end_date) as $date) {
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
}
