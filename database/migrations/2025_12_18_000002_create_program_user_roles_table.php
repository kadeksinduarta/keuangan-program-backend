<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role', ['ketua', 'bendahara', 'anggota'])->default('anggota');
            $table->enum('status', ['pending', 'approved'])->default('pending');
            $table->timestamps();
            
            $table->unique(['program_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_user_roles');
    }
};

