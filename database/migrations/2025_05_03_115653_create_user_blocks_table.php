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
        Schema::create('user_blocks', function (Blueprint $table) {
            // Who is doing the blocking
            $table->foreignId('blocker_id')
                  ->constrained('users')
                  ->cascadeOnDelete();                      // Cascade on user deletion :contentReference[oaicite:0]{index=0}

            // Who is being blocked
            $table->foreignId('blocked_id')
                  ->constrained('users')
                  ->cascadeOnDelete();                      // Cascade on user deletion :contentReference[oaicite:1]{index=1}

            // When the block happened
            $table->timestamp('blocked_at')->useCurrent();  // Auto‑timestamp :contentReference[oaicite:2]{index=2}
            $table->timestamps();

            // Prevent duplicate blocks
            $table->primary(['blocker_id', 'blocked_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
