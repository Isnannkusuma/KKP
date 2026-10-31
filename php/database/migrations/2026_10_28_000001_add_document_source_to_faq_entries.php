<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faq_entries', function (Blueprint $table) {
            $table->uuid('source_document_id')->nullable()->after('source');
            $table->index('source_document_id');
            $table->foreign('source_document_id')->references('id')->on('documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('faq_entries', function (Blueprint $table) {
            $table->dropForeign(['source_document_id']);
            $table->dropIndex(['source_document_id']);
            $table->dropColumn('source_document_id');
        });
    }
};
