<?php

namespace Database\Seeders;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Enums\SubjectStage;
use App\Enums\TimetablePattern;
use App\Models\Shift;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ShiftSeeder extends Seeder
{
    /**
     * 過去1週間〜2週間先（日曜休み）。各限に10人前後の講師を入れ、席ごとに生徒（学年・科目）を割り当てる
     */
    public function run(): void
    {
        $teachers = User::teachers()->active()->with('subjects')->get();
        $staff = User::where('role', User::ROLE_STAFF)->first();
        $mainTeacher = $teachers->firstWhere('email', 'teacher@example.com');
        $slotsByPattern = TimeSlot::orderBy('sort_order')->get()->groupBy(fn (TimeSlot $slot) => $slot->pattern->value);
        $subjectsByStage = Subject::all()->groupBy(fn (Subject $subject) => $subject->stage->value);
        $now = now();
        $rows = [];

        $row = fn (User $teacher, string $date, TimeSlot $slot, int $seat, ?Grade $grade, ?Subject $subject) => [
            'user_id' => $teacher->id,
            'date' => $date,
            'time_slot_id' => $slot->id,
            'seat' => $seat,
            'grade' => $grade?->value,
            'subject_id' => $subject?->id,
            'updated_by' => $staff?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];

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
                    $day = $date->toDateString();

                    // 小学生集団は1限に1人まで、担当中は満席
                    if (! $groupTaken && $byKind->has(SlotKind::ElementaryGroup->value) && random_int(1, 10) === 1) {
                        $rows[] = $row($teacher, $day, $byKind[SlotKind::ElementaryGroup->value], 1, null, null);
                        $groupTaken = true;

                        continue;
                    }

                    foreach ([1, 2] as $seat) {
                        // 1割くらいは右の席が空き
                        if ($seat === 2 && random_int(1, 10) === 1) {
                            continue;
                        }

                        if ($byKind->has(SlotKind::ElementaryFirst->value) && random_int(1, 10) <= 4) {
                            // 小学生：①②続けて見ることが多く、片方だけのこともある
                            $halves = Arr::random([
                                [SlotKind::ElementaryFirst, SlotKind::ElementarySecond],
                                [SlotKind::ElementaryFirst, SlotKind::ElementarySecond],
                                [SlotKind::ElementaryFirst],
                                [SlotKind::ElementarySecond],
                            ]);
                            foreach ($halves as $kind) {
                                [$grade, $subject] = $this->pickLesson($teacher, true, $subjectsByStage);
                                $rows[] = $row($teacher, $day, $byKind[$kind->value], $seat, $grade, $subject);
                            }
                        } else {
                            [$grade, $subject] = $this->pickLesson($teacher, false, $subjectsByStage);
                            $rows[] = $row($teacher, $day, $byKind[SlotKind::Secondary->value], $seat, $grade, $subject);
                        }
                    }
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Shift::insert($chunk);
        }
    }

    /**
     * 講師の担当できる科目から、生徒の学年と科目を選ぶ（担当科目がなければ全科目から）
     *
     * @param  Collection<string, Collection<int, Subject>>  $subjectsByStage
     * @return array{0: Grade, 1: Subject}
     */
    private function pickLesson(User $teacher, bool $elementary, Collection $subjectsByStage): array
    {
        $stages = $elementary ? [SubjectStage::Elementary] : [SubjectStage::Junior, SubjectStage::Senior];

        $candidates = $teacher->subjects->filter(fn (Subject $subject) => in_array($subject->stage, $stages, true));
        /** @var Subject $subject */
        $subject = $candidates->isNotEmpty()
            ? $candidates->random()
            : $subjectsByStage->get($elementary ? SubjectStage::Elementary->value : SubjectStage::Junior->value)->random();

        $grade = Arr::random(Grade::ofStage($subject->stage));

        return [$grade, $subject];
    }
}
