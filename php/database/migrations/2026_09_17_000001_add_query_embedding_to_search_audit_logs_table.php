<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE search_audit_logs ADD COLUMN query_embedding vector(768)');
        DB::statement('CREATE INDEX search_audit_logs_query_embedding_idx ON search_audit_logs USING hnsw (query_embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS search_audit_logs_query_embedding_idx');
        DB::statement('ALTER TABLE search_audit_logs DROP COLUMN IF EXISTS query_embedding');
    }
};
