<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 「急な代講の連絡を受け取る」の設定（最初は全員が受け取る）
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('accepts_urgent_requests')->default(true)->after('is_active');
        });

        // 同じ依頼に、リマインドや至急の連絡を二重に送らないための記録
        Schema::table('substitute_requests', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('staff_notified_at');
            $table->timestamp('urgent_notified_at')->nullable()->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('substitute_requests', function (Blueprint $table) {
            $table->dropColumn(['reminded_at', 'urgent_notified_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('accepts_urgent_requests');
        });
    }
};
