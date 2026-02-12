<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración para agregar índices estratégicos al sistema de visitas
 * 
 * Estos índices mejoran significativamente el rendimiento de las consultas más frecuentes:
 * - Búsquedas por fecha (visitas de hoy, por período)
 * - Búsquedas por estado (visitas activas/cerradas)
 * - Búsquedas por departamento
 * - Búsquedas por visitante
 * - Consultas de estadísticas
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Índice compuesto para estado y fecha (visitas por estado)
            $table->index(['status_id', 'created_at'], 'idx_visits_status_created');
            
            // Índice para búsquedas por departamento
            $table->index('department', 'idx_visits_department');
            
            // Índice compuesto para departamento y fecha (estadísticas por departamento)
            $table->index(['department', 'created_at'], 'idx_visits_dept_date');
            
            // Índice para usuario que cierra la visita
            $table->index('closed_by', 'idx_visits_closed_by');
            
            // Índice para búsquedas por placa de vehículo
            $table->index('vehicle_plate', 'idx_visits_vehicle_plate');
            
            // Índice para email de persona a visitar (notificaciones)
            $table->index('person_to_visit_email', 'idx_visits_email');
            
            // Índice para fecha de finalización
            $table->index('end_at', 'idx_visits_end_at');
        });

        Schema::table('visitors', function (Blueprint $table) {
            // Índice para búsquedas por nombre (búsqueda más común)
            $table->index('name', 'idx_visitors_name');
            
            // Índice para búsquedas por documento de identidad
            $table->index('identity_document', 'idx_visitors_identity');
            
            // Índice para fecha de creación (visitantes nuevos/recientes)
            $table->index('created_at', 'idx_visitors_created_at');
            
            // Índice para usuario que crea el visitante
            $table->index('user_id', 'idx_visitors_user_id');
            
            // Índice compuesto para búsquedas por nombre y apellido
            $table->index(['name', 'lastName'], 'idx_visitors_fullname');
            
            // Índice para email
            $table->index('email', 'idx_visitors_email');
        });

        Schema::table('visit_visitor', function (Blueprint $table) {
            // Índices para la tabla pivote (muy importantes para rendimiento)
            $table->index('visit_id', 'idx_visit_visitor_visit');
            $table->index('visitor_id', 'idx_visit_visitor_visitor');
            
            // Índice compuesto único para evitar duplicados
            $table->unique(['visit_id', 'visitor_id'], 'unq_visit_visitor');
        });

        Schema::table('users', function (Blueprint $table) {
            // Índice para búsquedas por email (login)
            $table->index('email', 'idx_users_email');
            
            // Índice para usuarios activos
            $table->index('email_verified_at', 'idx_users_verified');
            
            // Índice para fecha de creación
            $table->index('created_at', 'idx_users_created_at');
        });

        Schema::table('visit_statuses', function (Blueprint $table) {
            // Índice para el campo name (consultas por estado)
            $table->index('name', 'idx_visit_statuses_name');
        });

        // Agregar índice de texto completo para búsquedas avanzadas (solo MySQL)
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE visits ADD FULLTEXT(namePersonToVisit, department, reason)');
            DB::statement('ALTER TABLE visitors ADD FULLTEXT(name, lastName)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('idx_visits_status_created');
            $table->dropIndex('idx_visits_department');
            $table->dropIndex('idx_visits_dept_date');
            $table->dropIndex('idx_visits_closed_by');
            $table->dropIndex('idx_visits_vehicle_plate');
            $table->dropIndex('idx_visits_email');
            $table->dropIndex('idx_visits_end_at');
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->dropIndex('idx_visitors_name');
            $table->dropIndex('idx_visitors_identity');
            $table->dropIndex('idx_visitors_created_at');
            $table->dropIndex('idx_visitors_user_id');
            $table->dropIndex('idx_visitors_fullname');
            $table->dropIndex('idx_visitors_email');
        });

        Schema::table('visit_visitor', function (Blueprint $table) {
            $table->dropIndex('idx_visit_visitor_visit');
            $table->dropIndex('idx_visit_visitor_visitor');
            $table->dropUnique('unq_visit_visitor');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_email');
            $table->dropIndex('idx_users_verified');
            $table->dropIndex('idx_users_created_at');
        });

        Schema::table('visit_statuses', function (Blueprint $table) {
            $table->dropIndex('idx_visit_statuses_name');
        });

        // Remover índices de texto completo
        if (DB::connection()->getDriverName() === 'mysql') {
            // Necesario manejar errores si los índices no existen
            try {
                DB::statement('ALTER TABLE visits DROP INDEX namePersonToVisit');
            } catch (\Exception $e) {
                // Index might not exist
            }
            try {
                DB::statement('ALTER TABLE visitors DROP INDEX name');
            } catch (\Exception $e) {
                // Index might not exist
            }
        }
    }
};