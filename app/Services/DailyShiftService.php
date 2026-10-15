<?php

namespace App\Services;

use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use App\Models\ClosedDay;
use App\Models\Shift;
use App\Models\Term;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyShiftService
{
    public function __construct(private readonly TermTimetableService $terms) {}

    /**
     * その日にすでにあるシフトの時間割
     */
    public function existingPattern(CarbonInterface $date): ?TimetablePattern
    {
        $shift = Shift::with('timeSlot')->where('date', $date->toDateString())->first();

        return $shift?->timeSlot->pattern;
    }

    /**
     * その日に使う時間割（シフトがあればそれ、なければ曜日から）
     */
    public function patternFor(CarbonInterface $date): ?TimetablePattern
    {
        return $this->existingPattern($date) ?? TimetablePattern::defaultFor($date);
    }

    /**
     * その日に選べる時間割か（曜日どおりか講習。日曜は講習のみ）
     */
    public function canUsePattern(CarbonInterface $date, TimetablePattern $pattern): bool
    {
        return $pattern === TimetablePattern::Intensive || TimetablePattern::defaultFor($date) === $pattern;
    }

    /**
     * 1マス（講師×日付×限×席）を保存する。ここで保存したシフトは「個別に変更」になる
     *
     * @param  array<string, mixed>  $data
     * @return list<string> 警告
     */
    public function saveCell(
        CarbonInterface $date,
        TimetablePattern $pattern,
        User $teacher,
        int $period,
        int $seat,
        string $mode,
        array $data,
        User $editor,
    ): array {
        $day = $date->toDateString();

        if (ClosedDay::where('date', $day)->exists()) {
            throw ValidationException::withMessages(['cell' => 'この日は休みです。先に休みを解除してください。']);
        }
        if (! $this->canUsePattern($date, $pattern)) {
            throw ValidationException::withMessages(['cell' => $date->isSunday()
                ? '日曜は講習のときだけシフトを入れられます。'
                : 'この日は「'.TimetablePattern::defaultFor($date)?->label().'」か「講習」の時間割を選んでください。']);
        }
        $existing = $this->existingPattern($date);
        if ($existing !== null && $existing !== $pattern) {
            throw ValidationException::withMessages(['cell' => "この日はすでに「{$existing->label()}」の時間割でシフトがあります。切り替えるときは、先にこの日のシフトをすべて消してください。"]);
        }

        $slots = TimeSlot::ofPattern($pattern)->where('period', $period)->get()
            ->keyBy(fn (TimeSlot $slot) => $slot->kind->value);
        if ($slots->isEmpty()) {
            throw ValidationException::withMessages(['cell' => 'この時間割にない限です。']);
        }

        $new = CellInput::parse($slots, $seat, $mode, $data);

        DB::transaction(function () use ($day, $teacher, $slots, $seat, $mode, $new, $editor) {
            $groupSlotId = $slots->get(SlotKind::ElementaryGroup->value)?->id;

            // 今の席の授業と、同じ限の小学生集団（集団にするときはその限の授業すべて）
            $query = Shift::with('substituteRequests')
                ->where('date', $day)
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
            $targets = $query->lockForUpdate()->get();

            if ($targets->contains(fn (Shift $shift) => $shift->substituteRequests->isNotEmpty())) {
                throw ValidationException::withMessages(['cell' => '代講依頼が出ている授業は、ここでは変更できません。']);
            }
            Shift::whereIn('id', $targets->modelKeys())->delete();

            $others = Shift::with('timeSlot')->where('date', $day)->where('user_id', $teacher->id)->get();
            $entries = $others->toBase()
                ->map(fn (Shift $shift) => ['slot' => $shift->timeSlot, 'seats' => $shift->occupiedSeats()])
                ->concat(array_map(fn (array $item) => [
                    'slot' => $item[0],
                    'seats' => $item[0]->kind->isGroup() ? Shift::MAX_SEATS : 1,
                ], $new))
                ->values();
            if (SeatCalculator::peak($entries) > Shift::MAX_SEATS) {
                throw ValidationException::withMessages(['cell' => '同じ時間に見る生徒が2人を超えます。']);
            }

            foreach ($new as [$slot, $lessonSeat, $grade, $subject]) {
                $shift = new Shift([
                    'user_id' => $teacher->id,
                    'date' => $day,
                    'time_slot_id' => $slot->id,
                    'seat' => $lessonSeat,
                    'grade' => $grade,
                    'subject_id' => $subject?->id,
                ]);
                $shift->is_manual = true;
                $shift->updated_by = $editor->id;
                $shift->save();
            }
        });

        return CellInput::warnings($teacher, $new);
    }

    /**
     * その日を休みにする（シフトを消して休みの日に登録）
     *
     * @return int 消したシフトの数
     */
    public function close(CarbonInterface $date, ?string $note): int
    {
        return DB::transaction(function () use ($date, $note) {
            $deleted = $this->deleteShiftsOf($date);
            ClosedDay::firstOrCreate(['date' => $date->toDateString()], ['note' => $note]);

            return $deleted;
        });
    }

    /**
     * 休みを解除し、その日だけ時間割からシフトを作り直す
     *
     * @return int 作ったシフトの数
     */
    public function reopen(CarbonInterface $date, User $editor): int
    {
        ClosedDay::where('date', $date->toDateString())->delete();

        $term = Term::containing($date);
        if ($term === null) {
            return 0;
        }

        return $this->terms->apply($term, $date, $editor, $date)['created'];
    }

    /**
     * その日のシフトをすべて消す（時間割を切り替えるとき）
     *
     * @return int 消したシフトの数
     */
    public function clear(CarbonInterface $date): int
    {
        return DB::transaction(fn () => $this->deleteShiftsOf($date));
    }

    private function deleteShiftsOf(CarbonInterface $date): int
    {
        $shifts = Shift::with('substituteRequests')->where('date', $date->toDateString())->lockForUpdate()->get();

        if ($shifts->contains(fn (Shift $shift) => $shift->substituteRequests->isNotEmpty())) {
            throw ValidationException::withMessages(['date' => '代講依頼が出ている授業があるため、この日のシフトは消せません。']);
        }

        return Shift::whereIn('id', $shifts->modelKeys())->delete();
    }
}
