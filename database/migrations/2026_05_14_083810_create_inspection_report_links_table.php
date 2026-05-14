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
        Schema::create('inspection_report_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inspection_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('token')->unique();

            // optional short code
            $table->string('code', 20)->nullable()->unique();

            // keamanan
            $table->timestamp('expired_at');

            $table->timestamp('first_opened_at')->nullable();

            $table->string('device_hash')->nullable();

            $table->ipAddress('ip_address')->nullable();

            $table->text('user_agent')->nullable();

            // status
            $table->boolean('is_active')->default(true);

            // tracking
            $table->unsignedInteger('access_count')->default(0);

            $table->timestamp('last_accessed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_report_links');
    }
};
