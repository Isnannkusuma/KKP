<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('search_audit_logs', function (Blueprint $table) {
            $table->string('feedback', 16)->nullable()->after('response_status');
            $table->timestamp('feedback_at')->nullable()->after('feedback');
            $table->index('feedback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('search_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['feedback']);
            $table->dropColumn(['feedback', 'feedback_at']);
        });
    }
};
