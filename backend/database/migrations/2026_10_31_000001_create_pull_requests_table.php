<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_pr_number');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('author');
            $table->string('html_url');
            $table->timestamp('merged_at');
            $table->string('github_delivery_id')->unique();
            $table->timestamps();

            $table->unique(['repository_id', 'github_pr_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
