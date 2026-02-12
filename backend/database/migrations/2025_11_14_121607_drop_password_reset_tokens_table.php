<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Eliminar tabla password_reset_tokens porque:
     * - No hay implementación de "olvidé mi contraseña"
     * - Los usuarios usan Microsoft Auth (SSO) principalmente
     * - Los usuarios manuales son gestionados por Admin
     * - No se necesita funcionalidad de reset de contraseñas
     */
    public function up(): void
    {
        // Eliminar tabla password_reset_tokens si existe
        Schema::dropIfExists('password_reset_tokens');
    }

    /**
     * Reverse the migrations.
     * 
     * Restaurar tabla password_reset_tokens en caso de rollback
     */
    public function down(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
