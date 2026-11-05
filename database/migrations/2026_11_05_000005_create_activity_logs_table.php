<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Read-only audit trail: rows are inserted, never updated or deleted (no updated_at).
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);   // reservation / account
            $table->string('event', 50);
            $table->string('actor_type', 20); // guest / admin / system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['category', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
