<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('faq_entries')->where('source', 'document')->update(['source_document_id' => null]);

        Schema::create('faq_source_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->string('file_type', 10);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('faq_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::table('faq_entries', function (Blueprint $table) {
            $table->dropForeign(['source_document_id']);
        });

        Schema::table('faq_entries', function (Blueprint $table) {
            $table->foreign('source_document_id')
                ->references('id')
                ->on('faq_source_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('faq_entries')->whereNotNull('source_document_id')->update(['source_document_id' => null]);

        Schema::table('faq_entries', function (Blueprint $table) {
            $table->dropForeign(['source_document_id']);
        });

        Schema::table('faq_entries', function (Blueprint $table) {
            $table->foreign('source_document_id')->references('id')->on('documents')->nullOnDelete();
        });

        Schema::dropIfExists('faq_source_documents');
    }
};
