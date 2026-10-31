<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->text('summary')->nullable();
            $table->string('status');
            $table->jsonb('ai_raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique('pull_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_entries');
    }
};
