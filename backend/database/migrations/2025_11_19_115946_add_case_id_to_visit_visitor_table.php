<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega el campo case_id a la tabla visit_visitor para vincular
    * visitas misionales con casos registrados en el sistema externo de alertas
     */
    public function up(): void
    {
        Schema::table('visit_visitor', function (Blueprint $table) {
            // Agregar columna case_id que referencia a cases en el sistema externo
            $table->unsignedBigInteger('case_id')
                ->nullable()
                ->after('visitor_id')
                ->comment('ID del caso en el sistema externo (cases.id). NULL = sin alerta registrada');
            
            // Agregar índice para mejorar performance en consultas
            $table->index('case_id', 'idx_visit_visitor_case_id');
            
            // NOTA: No agregamos foreign key porque es a otra base de datos
            // La integridad se maneja a nivel de aplicación en AlertService
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visit_visitor', function (Blueprint $table) {
            // Eliminar índice primero
            $table->dropIndex('idx_visit_visitor_case_id');
            
            // Luego eliminar columna
            $table->dropColumn('case_id');
        });
    }
};
