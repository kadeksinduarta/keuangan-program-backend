<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // create, update, delete, approve, dll
            $table->string('module'); // RAB_ITEM, TRANSACTION, PROGRAM
            $table->unsignedBigInteger('module_id'); // ID dari record yang diubah
            $table->json('before_data')->nullable(); // Data sebelum perubahan
            $table->json('after_data')->nullable(); // Data setelah perubahan
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            $table->index(['module', 'module_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

