<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Eliminar tabla sessions porque:
     * - La app usa JWT para autenticación (stateless)
     * - Cambiamos SESSION_DRIVER a 'cookie'
     * - No necesitamos persistir sesiones en base de datos
     */
    public function up(): void
    {
        // Eliminar tabla sessions si existe
        Schema::dropIfExists('sessions');
    }

    /**
     * Reverse the migrations.
     * 
     * Restaurar tabla sessions en caso de rollback
     */
    public function down(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
};
