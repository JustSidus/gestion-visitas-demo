<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Convierte los campos building y floor de string a integer (unsigned)
     * Mantiene nulls para casos misionales
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Convertir strings a integers, manteniendo nulls
            // 1. Actualizar valores vacíos a NULL antes de cambiar el tipo
            DB::table('visits')
                ->where('building', '')
                ->orWhereNull('building')
                ->update(['building' => null]);
            
            DB::table('visits')
                ->where('floor', '')
                ->orWhereNull('floor')
                ->update(['floor' => null]);

            // 2. Cambiar el tipo de columna
            $table->unsignedTinyInteger('building')
                ->nullable()
                ->change()
                ->comment('Edificio de la visita (1-4)');
            
            $table->unsignedTinyInteger('floor')
                ->nullable()
                ->change()
                ->comment('Piso de la visita (1-4 según edificio)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Convertir de vuelta a string
            $table->string('building')
                ->nullable()
                ->change()
                ->comment('Edificio de la visita');
            
            $table->string('floor')
                ->nullable()
                ->change()
                ->comment('Piso de la visita');
        });
    }
};
