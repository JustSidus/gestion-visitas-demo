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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // quien registra la visita
            $table->foreignId('departament_id')->constrained('departaments')->onDelete('cascade');
            $table->string('namePersonToVisit');
            $table->string('reason');
            $table->foreignId('status_id')->constrained('visit_statuses'); // estado de la visita
            $table->timestamp('end_at')->nullable();
            $table->timestamps(); // incluye created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
