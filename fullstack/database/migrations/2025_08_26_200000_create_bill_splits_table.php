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
            $table->decimal('total_before_tip', 10, 2);
            $table->decimal('tip_amount', 10, 2)->default(0);
            $table->decimal('total_after_tip', 10, 2);
            $table->timestamps();
        });

        Schema::create('bill_split_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_split_id')->constrained('bill_splits')->onDelete('cascade');
            $table->string('name');
            $table->decimal('share_before_tip', 10, 2);
            $table->decimal('share_tip', 10, 2);
            $table->decimal('share_total', 10, 2);
            $table->boolean('paid')->default(false);
            $table->timestamps();
        });

        Schema::create('bill_split_item_participant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_split_id')->constrained('bill_splits')->onDelete('cascade');
            $table->foreignId('participant_id')->constrained('bill_split_participants')->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_split_item_participant');
        Schema::dropIfExists('bill_split_participants');
        Schema::dropIfExists('bill_splits');
    }
};
