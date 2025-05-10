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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            // the user who initiated the request
            $table->unsignedBigInteger('user_id')->constrained()->onDelete('cascade');
            
            // polymorphic target (e.g. App\Models\User)
            $table->unsignedBigInteger('requestable_id');
            $table->string('requestable_type');
            
            // request state & timestamps
            $table->enum('state', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('responded_at')->nullable();
            
            $table->timestamps();
            
            // optional index for faster lookups
            $table->index(['requestable_type', 'requestable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
