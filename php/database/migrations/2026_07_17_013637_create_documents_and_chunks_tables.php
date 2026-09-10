<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->string('file_type', 10);
            $table->unsignedBigInteger('file_size');
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->enum('access_level', ['public', 'internal', 'confidential'])->default('public');
            $table->enum('status', ['pending', 'extracting', 'cleaning', 'embedding', 'ready', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('total_chunks')->default(0);
            $table->timestamps();
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->text('content');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        // Tambah kolom vector untuk pgvector (Dimensi 768)
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(768)');

        // Index HNSW untuk similarity search yang cepat
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');

        // A. Tambah kolom tsvector untuk menyimpan indeks kata kunci
        DB::statement('ALTER TABLE document_chunks ADD COLUMN search_vector tsvector');

        // B. Buat Index GIN agar pencarian keyword (BM25-like) sangat cepat
        DB::statement('CREATE INDEX document_chunks_search_idx ON document_chunks USING gin (search_vector)');

        // C. Buat Trigger Otomatis: 
        // Setiap kali ada INSERT atau UPDATE pada kolom 'content', 
        // kolom 'search_vector' akan otomatis ter-generate.
        // PENTING: 'pg_catalog.simple' digunakan agar angka dan simbol (seperti "No. 123" atau "VII/2024") 
        // TIDAK dihapus oleh stemmer, sehingga pencarian nomor surat tetap akurat.
        DB::statement("
            CREATE TRIGGER tsvector_update 
            BEFORE INSERT OR UPDATE ON document_chunks 
            FOR EACH ROW 
            EXECUTE FUNCTION tsvector_update_trigger(search_vector, 'pg_catalog.simple', content)
        ");
    }

    public function down(): void
    {
        // Hapus trigger terlebih dahulu sebelum drop tabel untuk menghindari error
        DB::statement('DROP TRIGGER IF EXISTS tsvector_update ON document_chunks');

        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('documents');
    }
};
