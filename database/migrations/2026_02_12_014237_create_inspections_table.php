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
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // 🔴 inspection_id hanya reference ke sistem eksternal
            $table->unsignedBigInteger('inspection_id')->index()->nullable();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('inspector_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->enum('status', [
                'draft',
                'pending',
                'accepted',         // diterima oleh inspektor
                'on_the_way',       // inspektor sedang menuju lokasi
                'arrived',          // inspektor sampai di lokasi
                'in_progress',
                'paused',
                'under_review',
                'approved',
                'rejected',
                'revision',
                'completed',
                'cancelled'
            ])->default('draft');

            $table->dateTime('inspection_date');
            $table->text('notes')->nullable();
            $table->string('reference')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
