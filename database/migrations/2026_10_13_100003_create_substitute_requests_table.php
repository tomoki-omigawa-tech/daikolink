<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substitute_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('substitute_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'applied', 'confirmed', 'cancelled', 'expired'])->default('open');
            // 代講してほしい生徒数（1 or 2）。小学生集団は NULL
            $table->unsignedTinyInteger('student_count')->nullable();
            $table->string('subject', 30)->nullable();
            $table->string('grade', 20)->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('handover_note');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('staff_notified_at')->nullable();
            $table->timestamps();

            $table->index(['shift_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitute_requests');
    }
};
