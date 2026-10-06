<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faq_entries', function (Blueprint $table) {
            $table->string('source', 16)->default('generated')->after('status');
            $table->uuid('search_audit_log_id')->nullable()->unique()->after('source');
        });

        Schema::table('search_audit_logs', function (Blueprint $table) {
            $table->timestamp('manual_answered_at')->nullable()->after('feedback_at');
            $table->string('manual_answered_by')->nullable()->after('manual_answered_at');
        });
    }

    public function down(): void
    {
        Schema::table('search_audit_logs', function (Blueprint $table) {
            $table->dropColumn(['manual_answered_at', 'manual_answered_by']);
        });

        Schema::table('faq_entries', function (Blueprint $table) {
            $table->dropUnique(['search_audit_log_id']);
            $table->dropColumn(['source', 'search_audit_log_id']);
        });
    }
};
