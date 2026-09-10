<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identitas User
            $table->string('user_type');          // 'email' atau 'nip'
            $table->string('user_identifier');    // email@kkp.go.id atau 198501012010011005
            $table->string('user_name')->nullable(); // Nama user (jika diketahui)

            // Pertanyaan & Jawaban
            $table->text('query');                // Pertanyaan user
            $table->integer('result_count');      // Berapa hasil yang ditemukan
            $table->json('result_document_ids')->nullable(); // UUID dokumen yang muncul di hasil

            // Konteks Akses
            $table->string('access_level_used');  // 'public', 'internal', atau 'confidential'
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // Index untuk pencarian cepat
            $table->index('user_identifier');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_audit_logs');
    }
};
