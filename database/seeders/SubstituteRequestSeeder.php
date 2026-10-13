<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Models\Shift;
use App\Models\SubstituteRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class SubstituteRequestSeeder extends Seeder
{
    /**
     * 募集中の代講依頼を5件（うち1件はデモ用講師の依頼）作る
     */
    public function run(): void
    {
        $mainTeacher = User::where('email', 'teacher@example.com')->first();
        $futureShifts = Shift::with('timeSlot')
            ->where('date', '>', today()->toDateString())
            ->inRandomOrder()
            ->get();

        $targets = collect();
        $own = $futureShifts->firstWhere('user_id', $mainTeacher?->id);
        if ($own) {
            $targets->push($own);
        }
        $targets = $targets->merge(
            $futureShifts->reject(fn (Shift $shift) => $shift->user_id === $mainTeacher?->id)->take(4)
        );

        $reasons = ['大学の試験のため', 'ゼミの発表準備のため', '体調不良のため', '家庭の用事のため', '就職説明会のため'];
        $notes = [
            '1人目：連立方程式の文章題（教科書p.52〜）。2人目：前回の小テストの解き直しから。',
            '1人目：関係代名詞の続き。2人目：宿題のワークp.30〜31の丸付けをお願いします。',
            '説明文の読解。段落ごとの要点をまとめる練習中です。漢字テストの範囲確認もお願いします。',
            '1人目：電流と電圧の計算。2人目：割合の文章題でつまずいているので図を使って説明してください。',
            '二次関数の最大・最小（場合分け）。英単語テストと長文1題。',
        ];

        foreach ($targets->values() as $i => $shift) {
            $elementary = $shift->timeSlot->kind->isElementary();
            $count = $shift->timeSlot->kind->isGroup() ? 1 : $shift->occupiedSeats();

            $grades = array_map(fn () => Arr::random($elementary ? ['小4', '小5', '小6'] : ['中1', '中2', '中3', '高1']), range(1, $count));
            $subjects = array_map(fn () => Arr::random($elementary ? ['算数', '国語'] : ['数学', '英語', '国語', '理科', '社会']), range(1, $count));

            $request = new SubstituteRequest([
                'shift_id' => $shift->id,
                'requester_id' => $shift->user_id,
                'student_count' => $shift->timeSlot->kind->isGroup() ? null : $shift->occupiedSeats(),
                'subject' => implode('・', $subjects),
                'grade' => implode('・', $grades),
                'reason' => $reasons[$i % count($reasons)],
                'handover_note' => $notes[$i % count($notes)],
            ]);
            $request->status = RequestStatus::Open;
            $request->save();
        }
    }
}
