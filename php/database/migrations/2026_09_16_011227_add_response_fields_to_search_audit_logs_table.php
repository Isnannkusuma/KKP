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
            $table->text('answer')->nullable()->after('query');
            $table->string('response_status')->nullable()->after('answer');
            $table->index('response_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('search_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['response_status']);
            $table->dropColumn(['answer', 'response_status']);
        });
    }
};
