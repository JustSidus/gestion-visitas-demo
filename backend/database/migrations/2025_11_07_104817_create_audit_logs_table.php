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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Usuario que realizó la acción
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            // Información de la acción
            $table->string('action'); // create, update, delete, login, etc.
            $table->string('resource_type')->nullable(); // visit, visitor, user, etc.
            $table->string('resource_id')->nullable(); // ID del recurso afectado
            
            // Datos del cambio
            $table->json('old_values')->nullable(); // Valores anteriores
            $table->json('new_values')->nullable(); // Valores nuevos
            
            // Información de la request
            $table->string('ip_address', 45)->nullable(); // IPv4/IPv6
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->string('request_method', 10)->nullable(); // GET, POST, etc.
            $table->text('request_url')->nullable();
            $table->integer('status_code')->nullable(); // HTTP status code
            $table->integer('duration_ms')->nullable(); // Duración en milliseconds
            
            // Metadata adicional
            $table->json('metadata')->nullable(); // Información adicional contextual
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->json('tags')->nullable(); // Tags para categorización ['security', 'data_change', etc.]
            
            $table->timestamps();
            
            // Índices para optimizar consultas comunes
            $table->index(['user_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['action', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index('created_at');
            
            // Índice compuesto para queries de auditoría complejas
            $table->index(['resource_type', 'action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
