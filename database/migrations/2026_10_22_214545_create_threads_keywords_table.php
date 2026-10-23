<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('threads_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 100)->collation('utf8mb4_bin');
            $table->timestamp('search_window_start');
            $table->timestamp('last_searched_at')->nullable();
            $table->unsignedInteger('last_result_count')->nullable();
            $table->timestamp('zero_results_since')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'keyword']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads_keywords');
    }
};
