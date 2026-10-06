<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // nama situs, misal "Portal SDMAO"
            $table->string('widget_key')->unique(); // identifier publik, dipasang di kode embed
            $table->string('secret_key'); // RAHASIA, dipakai situs lain sign token identitas. Jangan pernah expose ke frontend.
            $table->json('allowed_origins'); // ["https://portal.kkp.go.id"]
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('widget_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_clients');
    }
};
