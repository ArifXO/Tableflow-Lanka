<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // payer (diner)
            $table->decimal('subtotal',10,2);
            $table->decimal('tip_amount',10,2)->default(0);
            $table->decimal('total_amount',10,2);
            $table->json('split_details'); // per guest breakdown
            $table->string('status')->default('pending'); // pending|paid|failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
