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
        Schema::create('user_inspection_templates', function (Blueprint $table) {
            $table->id();
            // relasi ke user (dari backend management)
            // $table->unsignedBigInteger('user_id')->index();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // id template dari backend inspection
            $table->unsignedBigInteger('template_id')->index();

            // type template: report / form
            $table->enum('template_type', ['report', 'form'])->index();

            // nama custom dari user
            $table->string('name');

            // default template per user
            $table->boolean('is_default')->default(false);

            // status aktif / tidak
            $table->boolean('is_active')->default(true);

            // config override (json)
            $table->json('config')->nullable();

            $table->timestamps();

            // index tambahan biar query cepat
            $table->index(['user_id', 'template_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_inspection_templates');
    }
};
