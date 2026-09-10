<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop index lama (wajib sebelum drop kolom)
        DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_idx');
        
        // 2. Drop kolom embedding lama (yang bertipe text)
        DB::statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS embedding');
        
        // 3. Buat kolom baru dengan tipe vector(768) untuk model base
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(768)');
        
        // 4. Buat index HNSW untuk similarity search
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');
        
        \Log::info('✅ Kolom embedding berhasil diubah ke vector(768)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_idx');
        DB::statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding text');
    }
};