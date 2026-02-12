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
        Schema::table('visitors', function (Blueprint $table) {
            // Hacer nullable el campo identity_document
            // Cambiar de string a string nullable
            $table->string('identity_document')->nullable()->change();
            
            // Remover la constraint unique si existe (para permitir múltiples NULLs)
            // Nota: La constraint única se remueve implícitamente al cambiar a nullable
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            // Revertir: hacer NOT NULL nuevamente
            $table->string('identity_document')->nullable(false)->change();
        });
    }
};
