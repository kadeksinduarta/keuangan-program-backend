<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->string('name'); // Nama item/kegiatan
            $table->string('category')->nullable(); // Kategori: konsumsi, transport, akomodasi, dll
            $table->decimal('volume', 15, 2); // Volume
            $table->string('unit'); // Satuan: orang, paket, unit, dll
            $table->decimal('unit_price', 15, 2); // Harga satuan
            $table->decimal('total_budget', 15, 2); // Total = volume * unit_price
            $table->enum('status', ['belum_terpenuhi', 'sebagian_terpenuhi', 'terpenuhi'])->default('belum_terpenuhi');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_items');
    }
};

