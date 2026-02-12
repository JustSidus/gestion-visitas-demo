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
        // Quitar la columna departament_id de la tabla visits
        if (Schema::hasColumn('visits', 'departament_id')) {
            Schema::table('visits', function (Blueprint $table) {
                // Primero dropear la foreign key si existe
                // Comprobamos nombres comunes, pero si tu FK tiene otro nombre, ajústalo manualmente
                try {
                    $table->dropForeign(['departament_id']);
                } catch (\Exception $e) {
                    // ignorar si no existe
                }

                try {
                    $table->dropColumn('departament_id');
                } catch (\Exception $e) {
                    // ignorar si no existe
                }
            });
        }

        // Eliminar la tabla departaments si existe
        if (Schema::hasTable('departaments')) {
            Schema::dropIfExists('departaments');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurar la tabla departaments y la columna departament_id en visits
        if (! Schema::hasTable('departaments')) {
            Schema::create('departaments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('visits', 'departament_id')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->foreignId('departament_id')->nullable()->constrained('departaments')->onDelete('cascade');
            });
        }
    }
};
