<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->enum('pattern', ['weekday', 'saturday', 'intensive']);
            $table->unsignedTinyInteger('period');
            $table->enum('kind', ['secondary', 'elementary_first', 'elementary_second', 'elementary_group']);
            $table->string('name', 30);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['pattern', 'period', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
