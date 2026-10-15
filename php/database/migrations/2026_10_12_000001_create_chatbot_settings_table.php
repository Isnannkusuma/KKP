<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->text('custom_instructions')->nullable();
            $table->string('assistant_name')->nullable();
            $table->string('assistant_role')->nullable();
            $table->text('additional_info')->nullable();
            $table->string('tone')->default('default');
            $table->string('warmth')->default('default');
            $table->string('enthusiasm')->default('default');
            $table->string('emoji')->default('default');
            $table->string('layout')->default('default');
            $table->string('headings_lists')->default('default');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_settings');
    }
};
