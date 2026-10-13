<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substitute_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('substitute_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'withdrawn'])->default('pending');
            $table->timestamps();

            // 同じ依頼への重複応募を防ぐ
            $table->unique(['substitute_request_id', 'applicant_id'], 'sub_applications_request_applicant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitute_applications');
    }
};
