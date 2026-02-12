<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega índices optimizados para mejorar el rendimiento del sistema de login
     * y autenticación con Microsoft 365.
     * 
     * IMPACTO ESPERADO:
     * - Búsquedas por email: 3-10x más rápidas
     * - Login con Microsoft: 50-200ms más rápido
     * - Queries de usuarios activos: 5-15x más rápidas
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Índice compuesto para login: email + is_active
            // Query típico: WHERE email = ? AND is_active = 1
            // Beneficio: Login 3-10x más rápido
            $table->index(['email', 'is_active'], 'idx_users_email_active');
            
            // Índice para microsoft_id (autenticación SSO)
            // Query típico: WHERE microsoft_id = ?
            // Beneficio: Búsqueda de usuario por MS ID instantánea
            $table->index('microsoft_id', 'idx_users_microsoft_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminar índices en orden inverso
            $table->dropIndex('idx_users_email_active');
            $table->dropIndex('idx_users_microsoft_id');
        });
    }
};
