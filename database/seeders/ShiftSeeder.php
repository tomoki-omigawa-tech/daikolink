<?php

namespace Database\Seeders;

use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use App\Models\Shift;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ShiftSeeder extends Seeder
{
    /**
     * 過去1週間〜2週間先（日曜休み）。各限に10人前後の講師を、実際にありうる席の組み合わせで割り当てる
     */
    public function run(): void
    {
        $teachers = User::teachers()->active()->get();
        $staff = User::where('role', User::ROLE_STAFF)->first();
        $mainTeacher = $teachers->firstWhere('email', 'teacher@example.com');
        $slotsByPattern = TimeSlot::orderBy('sort_order')->get()->groupBy(fn (TimeSlot $slot) => $slot->pattern->value);
        $now = now();
        $rows = [];

        foreach (CarbonPeriod::create(today()->subDays(7), today()->addDays(14)) as $date) {
            $pattern = TimetablePattern::defaultFor($date);
            if ($pattern === null) {
                continue;
            }

            $periods = $slotsByPattern->get($pattern->value, collect())->groupBy('period');

            foreach ($periods->values() as $index => $periodSlots) {
                $byKind = $periodSlots->keyBy(fn (TimeSlot $slot) => $slot->kind->value);
                $assigned = $teachers->random(min(10, $teachers->count()));
                $groupTaken = false;

                // デモ用アカウントは毎日その日の最初の2つの限に必ず入れる
                if ($mainTeacher && $index < 2 && ! $assigned->contains($mainTeacher)) {
                    $assigned->push($mainTeacher);
                }

                foreach ($assigned as $teacher) {
                    $config = $this->pickConfig($byKind, $groupTaken);
                    if ($config[0][0] === SlotKind::ElementaryGroup) {
                        $groupTaken = true;
                    }

                    foreach ($config as [$kind, $count]) {
                        $rows[] = [
                            'user_id' => $teacher->id,
                            'date' => $date->toDateString(),
                            'time_slot_id' => $byKind[$kind->value]->id,
                            'student_count' => $count,
                            'updated_by' => $staff?->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Shift::insert($chunk);
        }
    }

    /**
     * 1人の講師がその限で担当する授業枠と生徒数の組み合わせ（どの時刻でも生徒は2人まで）
     *
     * @param  Collection<string, TimeSlot>  $byKind
     * @return list<array{0: SlotKind, 1: int|null}>
     */
    private function pickConfig(Collection $byKind, bool $groupTaken): array
    {
        $s = SlotKind::Secondary;
        $e1 = SlotKind::ElementaryFirst;
        $e2 = SlotKind::ElementarySecond;

        $configs = [
            [[$s, 2]], [[$s, 2]], [[$s, 2]],
            [[$s, 1]],
        ];

        if ($byKind->has($e1->value)) {
            // 片方が中高生・片方が小学生①②（よくある形）
            $configs[] = [[$s, 1], [$e1, 1], [$e2, 1]];
            $configs[] = [[$s, 1], [$e1, 1], [$e2, 1]];
            // 両方小学生①②
            $configs[] = [[$e1, 2], [$e2, 2]];
            // 小学生の①か②が空き
            $configs[] = [[$s, 1], [$e1, 1]];
            $configs[] = [[$s, 1], [$e2, 1]];
        }

        if (! $groupTaken && $byKind->has(SlotKind::ElementaryGroup->value)) {
            $configs[] = [[SlotKind::ElementaryGroup, null]];
        }

        return Arr::random($configs);
    }
}
