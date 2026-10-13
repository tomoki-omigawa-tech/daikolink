<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('time_slot_id')->constrained()->restrictOnDelete();
            // 見ている生徒数（1 or 2）。小学生集団は NULL（満席扱い）
            $table->unsignedTinyInteger('student_count')->nullable()->default(2);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // 同じ講師の同じ授業枠の二重シフトをDBレベルで防ぐ
            $table->unique(['user_id', 'date', 'time_slot_id']);
            $table->index(['date', 'time_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
