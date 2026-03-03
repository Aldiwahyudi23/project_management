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
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
              // Data identitas customer
            $table->foreignId('customer_id')->constrained()->onDelete('cascade'); // Relasi ke tabel customers
            $table->foreignId('inspection_id')->nullable()->constrained('inspections')->onDelete('cascade');
        
             // Data lokasi inspeksi
            $table->string('inspection_area')->nullable(); // Area inspeksi
            $table->text('inspection_address')->nullable(); // Alamat lengkap inspeksi
            
            // Link Google Maps
            $table->text('link_maps')->nullable(); // Link Google Maps
            
            // Data orang yang memegang unit
            $table->string('unit_holder_name')->nullable(); // Nama yang pegang unit
            $table->string('unit_holder_phone')->nullable(); // No. HP yang pegang unit

            $table->json('settings')->nullable(); // mencantumkan catatan lain jika diperlukan dan tipe kendaraan
            
            $table->timestamps(); // created_at dan updated_at
            $table->softDeletes(); // Untuk soft delete
            
            // Index untuk pencarian
            $table->index('inspection_area');
            $table->index('unit_holder_name');
            // Tambahkan index untuk pencarian berdasarkan customer_id dan inspection_id
            $table->index(['customer_id', 'inspection_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
