<?php

namespace Database\Seeders;

use App\Enums\SubjectStage;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * 森塾で扱っている科目と、講師ごとの担当できる科目
     */
    public function run(): void
    {
        $subjects = [
            SubjectStage::Elementary->value => ['国語', '算数', '英語'],
            SubjectStage::Junior->value => ['国語', '数学', '英語', '理科', '社会'],
            SubjectStage::Senior->value => ['古典', '論表', '数ⅠA', '数ⅡB', '数Ⅲ', '数C', '物理基礎', '化学基礎', '生物基礎', '物理', '化学', '生物'],
            SubjectStage::Other->value => ['DOJO', 'キュレオ'],
        ];

        $order = 1;
        foreach ($subjects as $stage => $names) {
            foreach ($names as $name) {
                Subject::updateOrCreate(
                    ['stage' => $stage, 'name' => $name],
                    ['is_active' => true, 'sort_order' => $order++]
                );
            }
        }

        $all = Subject::all();
        $demo = [
            [SubjectStage::Elementary, '算数'],
            [SubjectStage::Junior, '数学'],
            [SubjectStage::Junior, '英語'],
            [SubjectStage::Senior, '数ⅠA'],
            [SubjectStage::Senior, '論表'],
        ];

        foreach (User::teachers()->get() as $teacher) {
            $ids = $teacher->email === 'teacher@example.com'
                ? $all->filter(fn (Subject $s) => in_array([$s->stage, $s->name], $demo, true))->pluck('id')
                : $all->random(random_int(3, 6))->pluck('id');

            $teacher->subjects()->sync($ids);
        }
    }
}
