<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para hacer assigned_carnet nullable
 * 
 * Permite que el carnet sea opcional en casos misionales
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Cambiar assigned_carnet para que sea nullable
            $table->integer('assigned_carnet')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Volver a establecer assigned_carnet como no nullable
            $table->integer('assigned_carnet')->nullable(false)->change();
        });
    }
};
