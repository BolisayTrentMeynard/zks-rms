<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // Guest accounts have user_id; walk-ins have no account, only a name and contact.
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_walk_in')->default(false);
            $table->string('guest_name')->nullable();
            $table->string('guest_contact', 30)->nullable();

            $table->date('reservation_date');
            $table->string('duration', 20);      // whole_day / half_day
            $table->string('booking_type', 20);  // pool / transient / room / venue_hall
            $table->unsignedSmallInteger('pax');
            $table->string('occasion');
            $table->string('status', 30);

            // 24-hour hold
            $table->dateTime('hold_expires_at')->nullable();
            $table->dateTime('hold_reminder_sent_at')->nullable();

            // One group per date: set only while the reservation blocks the date,
            // NULL when Expired/Cancelled. UNIQUE stops two blocking reservations on one date
            // (MySQL allows many NULLs in a unique index).
            $table->date('blocking_date')->nullable()->unique();

            // Reschedule / cancel request (status stays as is until Admin approves)
            $table->string('change_request', 20)->nullable();
            $table->date('requested_date')->nullable();
            $table->dateTime('change_requested_at')->nullable();
            $table->boolean('downpayment_forfeited')->default(false);

            $table->timestamps();

            $table->index('status');
            $table->index('reservation_date');
            $table->index('hold_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
