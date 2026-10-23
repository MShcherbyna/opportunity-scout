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
        Schema::create('threads_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('threads_keyword_id')->nullable()->constrained('threads_keywords')->nullOnDelete();
            $table->string('threads_post_id', 64);
            $table->text('text')->nullable();
            $table->string('permalink', 2048)->nullable();
            $table->string('username');
            $keyword = $table->string('keyword', 100);
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $keyword->collation('utf8mb4_bin');
            }
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique(['user_id', 'threads_post_id']);
            $table->index(['user_id', 'posted_at']);
            $table->index(['user_id', 'keyword']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads_posts');
    }
};
