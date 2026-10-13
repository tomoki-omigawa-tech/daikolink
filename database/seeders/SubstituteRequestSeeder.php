<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Models\Shift;
use App\Models\SubstituteRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubstituteRequestSeeder extends Seeder
{
    /**
     * 募集中の代講依頼を5件（うち1件はデモ用講師の依頼）作る。1依頼＝生徒1人の授業
     */
    public function run(): void
    {
        $mainTeacher = User::where('email', 'teacher@example.com')->first();
        $futureShifts = Shift::where('date', '>', today()->toDateString())->inRandomOrder()->get();

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
            '教科書p.52〜の続き。前回の小テストで間違えた問題の解き直しから。',
            '宿題のワークp.30〜31の丸付けをお願いします。',
            '文章題でつまずいているので、図を使って説明してください。',
            '単語テスト（範囲：Unit5）のあと、長文を1題。',
            '定期テスト前なので、学校のワークの質問対応をお願いします。',
        ];

        foreach ($targets->values() as $i => $shift) {
            $request = new SubstituteRequest([
                'shift_id' => $shift->id,
                'requester_id' => $shift->user_id,
                'reason' => $reasons[$i % count($reasons)],
                'handover_note' => $notes[$i % count($notes)],
            ]);
            $request->status = RequestStatus::Open;
            $request->save();
        }
    }
}
