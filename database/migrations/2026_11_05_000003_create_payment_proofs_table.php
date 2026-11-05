<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Image + status only. No payment amounts. A re-upload adds a new row; the newest is current.
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('path'); // on the private disk, never the public one
            $table->string('status', 20)->default('for_review'); // for_review / accepted / flagged
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
