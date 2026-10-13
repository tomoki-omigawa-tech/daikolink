<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * シフトを「生徒1人＝1行」に変更する
 * - 科目に区分（小学・中学・高校・その他）と使用中フラグを追加
 * - シフトに席・学年・科目を追加し、生徒数を削除
 * - 代講依頼の生徒数・科目・学年を削除（シフト側で分かるため）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->enum('stage', ['elementary', 'junior', 'senior', 'other'])->default('other')->after('id');
            $table->boolean('is_active')->default(true)->after('name');
            $table->unique(['stage', 'name']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedTinyInteger('seat')->default(1)->after('time_slot_id');
            $table->enum('grade', ['e3', 'e4', 'e5', 'e6', 'j1', 'j2', 'j3', 'h1', 'h2', 'h3'])->nullable()->after('seat');
            $table->foreignId('subject_id')->nullable()->after('grade')->constrained()->nullOnDelete();
            $table->unique(['user_id', 'date', 'time_slot_id', 'seat'], 'shifts_user_date_slot_seat_unique');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'date', 'time_slot_id']);
            $table->dropColumn('student_count');
        });

        Schema::table('substitute_requests', function (Blueprint $table) {
            $table->dropColumn(['student_count', 'subject', 'grade']);
        });
    }

    /**
     * 元の「講師×日付×授業枠＝1行＋生徒数」に戻す。
     * 同じ授業枠の2行（左右の席）は1行にまとめて生徒数にする（学年・科目と、まとめて消える行の代講依頼は失われる）
     */
    public function down(): void
    {
        Schema::table('substitute_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('student_count')->nullable()->after('status');
            $table->string('subject', 30)->nullable()->after('student_count');
            $table->string('grade', 20)->nullable()->after('subject');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedTinyInteger('student_count')->nullable()->default(2)->after('time_slot_id');
        });

        $groups = DB::table('shifts')
            ->select('user_id', 'date', 'time_slot_id', DB::raw('COUNT(*) AS cnt'), DB::raw('MIN(id) AS keep_id'))
            ->groupBy('user_id', 'date', 'time_slot_id')
            ->get();

        foreach ($groups as $group) {
            DB::table('shifts')->where('id', $group->keep_id)->update(['student_count' => min((int) $group->cnt, 2)]);
            DB::table('shifts')
                ->where('user_id', $group->user_id)
                ->where('date', $group->date)
                ->where('time_slot_id', $group->time_slot_id)
                ->where('id', '!=', $group->keep_id)
                ->delete();
        }

        Schema::table('shifts', function (Blueprint $table) {
            $table->unique(['user_id', 'date', 'time_slot_id']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropUnique('shifts_user_date_slot_seat_unique');
            $table->dropConstrainedForeignId('subject_id');
            $table->dropColumn(['seat', 'grade']);
        });

        // 小学の国語と中学の国語などが同じ名前になるので、頭に区分を付けて元の科目名に戻す
        DB::table('subjects')->where('stage', 'elementary')->update(['name' => DB::raw("CONCAT('小学', name)")]);
        DB::table('subjects')->where('stage', 'junior')->update(['name' => DB::raw("CONCAT('中学', name)")]);

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['stage', 'name']);
            $table->dropColumn(['stage', 'is_active']);
            $table->unique('name');
        });
    }
};
