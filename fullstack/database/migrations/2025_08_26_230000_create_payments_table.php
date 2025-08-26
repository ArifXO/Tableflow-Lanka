<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		// If an old payments table exists with a different schema, drop it first (dev convenience)
		if (Schema::hasTable('payments')) {
			Schema::drop('payments');
		}

		Schema::create('payments', function (Blueprint $table) {
			$table->id();
			$table->foreignId('order_id')->constrained()->onDelete('cascade');
			$table->decimal('amount', 10, 2);          // amount including any non-tip portion actually paid now
			$table->decimal('tip_amount', 10, 2)->default(0);
			$table->string('method');                  // cash | card | wallet
			$table->string('status')->default('paid'); // paid | pending | failed | refunded (extensible)
			$table->json('meta')->nullable();          // arbitrary gateway or split metadata
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('payments');
	}
};

