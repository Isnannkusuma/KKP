<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('cluster_label')->nullable();
            $table->integer('source_count')->default(0);
            $table->text('question');
            $table->text('answer');
            $table->string('status', 16)->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('cluster_label');
        });

        DB::statement('ALTER TABLE faq_entries ADD COLUMN question_embedding vector(768)');
        DB::statement('ALTER TABLE faq_entries ADD COLUMN answer_embedding vector(768)');
        DB::statement('CREATE INDEX faq_entries_question_embedding_idx ON faq_entries USING hnsw (question_embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_entries');
    }
};
