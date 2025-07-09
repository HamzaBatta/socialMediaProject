<?php

use App\Models\Highlight;
use App\Models\Status;
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
        Schema::create('status_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Status::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Highlight::class)->constrained()->cascadeOnDelete();
            $table->timestamp('added_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_highlights');
    }
};
