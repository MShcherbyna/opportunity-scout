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
        Schema::table('threads_posts', function (Blueprint $table) {
            $table->boolean('is_relevant')->default(false)->after('posted_at');
            $table->index(['user_id', 'is_relevant', 'posted_at']);
            $table->index(['user_id', 'is_relevant', 'created_at']);
        });

        Schema::table('threads_posts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'posted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('threads_posts', function (Blueprint $table) {
            $table->index(['user_id', 'posted_at']);
        });

        Schema::table('threads_posts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_relevant', 'posted_at']);
            $table->dropIndex(['user_id', 'is_relevant', 'created_at']);
            $table->dropColumn('is_relevant');
        });
    }
};
