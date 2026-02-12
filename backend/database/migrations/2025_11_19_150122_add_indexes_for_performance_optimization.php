<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar índices ignorando errores si ya existen
        try {
            DB::statement('CREATE INDEX idx_visits_active_mission ON visits (status_id, mission_case, created_at)');
        } catch (\Exception $e) {
            // Índice ya existe, ignorar
        }
        
        try {
            DB::statement('CREATE INDEX idx_visits_created_at ON visits (created_at)');
        } catch (\Exception $e) {
            // Índice ya existe, ignorar
        }
        
        try {
            DB::statement('CREATE INDEX idx_visit_visitor_case ON visit_visitor (visit_id, case_id)');
        } catch (\Exception $e) {
            // Índice ya existe, ignorar
        }
        
        try {
            DB::statement('CREATE INDEX idx_case_id ON visit_visitor (case_id)');
        } catch (\Exception $e) {
            // Índice ya existe, ignorar
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP INDEX idx_visits_active_mission ON visits');
        } catch (\Exception $e) {
            // Índice no existe, ignorar
        }
        
        try {
            DB::statement('DROP INDEX idx_visits_created_at ON visits');
        } catch (\Exception $e) {
            // Índice no existe, ignorar
        }
        
        try {
            DB::statement('DROP INDEX idx_visit_visitor_case ON visit_visitor');
        } catch (\Exception $e) {
            // Índice no existe, ignorar
        }
        
        try {
            DB::statement('DROP INDEX idx_case_id ON visit_visitor');
        } catch (\Exception $e) {
            // Índice no existe, ignorar
        }
    }
};
