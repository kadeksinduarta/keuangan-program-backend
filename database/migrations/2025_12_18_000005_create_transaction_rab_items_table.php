<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_rab_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
            $table->foreignId('rab_item_id')->constrained('rab_items')->onDelete('cascade');
            $table->decimal('amount', 15, 2); // Jumlah yang dialokasikan ke item RAB ini
            $table->timestamps();
            
            $table->index(['transaction_id', 'rab_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_rab_items');
    }
};

