<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * 架空データ（パスワードはすべて password）
     */
    public function run(): void
    {
        User::factory()->staff()->create([
            'name' => '教室 スタッフ',
            'email' => 'staff@example.com',
        ]);

        User::factory()->create([
            'name' => '講師 太郎',
            'email' => 'teacher@example.com',
        ]);

        User::factory()->count(14)->create();
        User::factory()->inactive()->count(2)->create();

        // ニックネーム（最後の数人はニックネームなし）
        $nicknames = ['ひょー', 'そうた', 'はっちゃん', 'ゆうき', 'あず', 'みっきー', 'たっくん', 'りな', 'けんけん', 'まっちゃん', 'ゆず', 'しゅん', 'なっつ'];
        User::teachers()->orderBy('id')->get()->each(function (User $teacher, int $i) use ($nicknames) {
            if (isset($nicknames[$i])) {
                $teacher->update(['nickname' => $nicknames[$i]]);
            }
        });

        $this->call([
            SubjectSeeder::class,
            TimeSlotSeeder::class,
            TermTimetableSeeder::class,
            SubstituteRequestSeeder::class,
        ]);
    }
}
