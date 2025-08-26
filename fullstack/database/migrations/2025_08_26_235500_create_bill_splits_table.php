<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bill_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade')->unique();
            $table->json('participants');
            $table->decimal('total_before_tip',10,2);
            $table->decimal('tip_amount',10,2)->default(0);
            $table->decimal('total_after_tip',10,2);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('bill_splits');
    }
};
