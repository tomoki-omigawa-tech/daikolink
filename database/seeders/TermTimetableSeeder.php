<?php

namespace Database\Seeders;

use App\Enums\Grade;
use App\Enums\SlotKind;
use App\Enums\SubjectStage;
use App\Enums\TimetablePattern;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermLesson;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\TermTimetableService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class TermTimetableSeeder extends Seeder
{
    /**
     * 今日を含む学期を作り、曜日ごとの時間割（各限10人前後）を登録して、1週間前からシフトに反映する
     */
    public function run(TermTimetableService $service): void
    {
        $term = $this->currentTerm();
        $teachers = User::teachers()->active()->with('subjects')->get();
        $staff = User::where('role', User::ROLE_STAFF)->firstOrFail();
        $mainTeacher = $teachers->firstWhere('email', 'teacher@example.com');
        $slotsByPattern = TimeSlot::orderBy('sort_order')->get()->groupBy(fn (TimeSlot $slot) => $slot->pattern->value);
        $subjectsByStage = Subject::all()->groupBy(fn (Subject $subject) => $subject->stage->value);

        foreach (range(1, 6) as $weekday) {
            $pattern = $weekday === 6 ? TimetablePattern::Saturday : TimetablePattern::Weekday;
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
                    $lesson = fn (SlotKind $kind, int $seat, ?Grade $grade, ?Subject $subject) => TermLesson::create([
                        'term_id' => $term->id,
                        'weekday' => $weekday,
                        'time_slot_id' => $byKind[$kind->value]->id,
                        'seat' => $seat,
                        'user_id' => $teacher->id,
                        'grade' => $grade,
                        'subject_id' => $subject?->id,
                    ]);

                    // 小学生集団は1限に1人まで、担当中は満席
                    if (! $groupTaken && $byKind->has(SlotKind::ElementaryGroup->value) && random_int(1, 10) === 1) {
                        $lesson(SlotKind::ElementaryGroup, 1, null, null);
                        $groupTaken = true;

                        continue;
                    }

                    foreach ([1, 2] as $seat) {
                        if ($seat === 2 && random_int(1, 10) === 1) {
                            continue;
                        }

                        if ($byKind->has(SlotKind::ElementaryFirst->value) && random_int(1, 10) <= 4) {
                            $halves = Arr::random([
                                [SlotKind::ElementaryFirst, SlotKind::ElementarySecond],
                                [SlotKind::ElementaryFirst, SlotKind::ElementarySecond],
                                [SlotKind::ElementaryFirst],
                                [SlotKind::ElementarySecond],
                            ]);
                            foreach ($halves as $kind) {
                                [$grade, $subject] = $this->pickLesson($teacher, true, $subjectsByStage);
                                $lesson($kind, $seat, $grade, $subject);
                            }
                        } else {
                            [$grade, $subject] = $this->pickLesson($teacher, false, $subjectsByStage);
                            $lesson(SlotKind::Secondary, $seat, $grade, $subject);
                        }
                    }
                }
            }
        }

        $service->apply($term, today()->subDays(7), $staff);
    }

    /**
     * 今日を含む学期（1学期：4〜8月、2学期：9〜12月、3学期：1〜3月）
     */
    private function currentTerm(): Term
    {
        $today = today();
        $year = $today->year;

        [$fiscalYear, $termNo, $start, $end] = match (true) {
            $today->month >= 4 && $today->month <= 8 => [$year, 1, "{$year}-04-01", "{$year}-08-31"],
            $today->month >= 9 => [$year, 2, "{$year}-09-01", "{$year}-12-31"],
            default => [$year - 1, 3, "{$year}-01-01", "{$year}-03-31"],
        };

        return Term::updateOrCreate(
            ['fiscal_year' => $fiscalYear, 'term_no' => $termNo],
            ['start_date' => $start, 'end_date' => $end]
        );
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

        return [Arr::random(Grade::ofStage($subject->stage)), $subject];
    }
}
