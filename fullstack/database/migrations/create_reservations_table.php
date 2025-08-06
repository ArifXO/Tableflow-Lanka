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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // User who made the reservation
            $table->foreignId('table_id')->constrained('tables')->onDelete('cascade'); // Reserved table
            $table->date('reservation_date'); // Date of reservation
            $table->time('reservation_time'); // Time of reservation
            $table->integer('party_size'); // Number of guests
            $table->string('status')->default('confirmed'); // confirmed, cancelled, completed
            $table->text('special_requests')->nullable(); // Special requests or notes
            $table->string('customer_name'); // Customer name
            $table->string('customer_email'); // Customer email
            $table->string('customer_phone')->nullable(); // Customer phone
            $table->timestamps();

            // Ensure one table can't be booked twice at the same time
            $table->unique(['table_id', 'reservation_date', 'reservation_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
