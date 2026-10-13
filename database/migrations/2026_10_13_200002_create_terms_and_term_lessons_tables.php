<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('term_no');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['fiscal_year', 'term_no']);
        });

        Schema::create('term_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            // 1（月）〜6（土）
            $table->unsignedTinyInteger('weekday');
            $table->foreignId('time_slot_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('seat')->default(1);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('grade', ['e3', 'e4', 'e5', 'e6', 'j1', 'j2', 'j3', 'h1', 'h2', 'h3'])->nullable();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['term_id', 'weekday', 'user_id', 'time_slot_id', 'seat'], 'term_lessons_unique');
            $table->index(['term_id', 'weekday']);
        });

        Schema::create('closed_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('note', 50)->nullable();
            $table->timestamps();
        });

        Schema::table('shifts', function (Blueprint $table) {
            // 時間割から作られたシフトは元の授業を持つ。個別に変更したシフトは is_manual = true で、時間割の反映で上書きしない
            $table->foreignId('term_lesson_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
            $table->boolean('is_manual')->default(false)->after('term_lesson_id');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('term_lesson_id');
            $table->dropColumn('is_manual');
        });

        Schema::dropIfExists('closed_days');
        Schema::dropIfExists('term_lessons');
        Schema::dropIfExists('terms');
    }
};
