<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * 科目マスタと、講師ごとの担当できる科目（2〜3科目）
     */
    public function run(): void
    {
        foreach (['算数', '国語', '数学', '英語', '理科', '社会'] as $i => $name) {
            Subject::updateOrCreate(['name' => $name], ['sort_order' => $i + 1]);
        }

        $subjects = Subject::all();

        foreach (User::teachers()->get() as $teacher) {
            $ids = $teacher->email === 'teacher@example.com'
                ? $subjects->whereIn('name', ['数学', '英語'])->pluck('id')
                : $subjects->random(random_int(2, 3))->pluck('id');

            $teacher->subjects()->sync($ids);
        }
    }
}
