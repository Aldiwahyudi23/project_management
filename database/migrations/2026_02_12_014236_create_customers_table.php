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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            // Data identitas customer
            $table->string('name'); // Nama customer
            $table->string('phone')->nullable()->unique(); // Nomor WhatsApp (harus unik)
            $table->string('email')->nullable()->unique(); // Email (opsional, unique jika ada)
            
            // Data alamat customer
            $table->text('address')->nullable(); // Alamat lengkap customer

            $table->timestamps(); // created_at dan updated_at
            $table->softDeletes(); // Untuk soft delete
            
            // Index untuk pencarian
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
