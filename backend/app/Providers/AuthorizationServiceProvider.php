<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Visit;
use App\Models\Visitor;
use App\Policies\VisitPolicy;
use App\Policies\VisitorPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * Proveedor de autorización y seguridad
 * 
 * Responsabilidades:
 * - Registrar políticas de autorización
 * - Definir Gates para permisos específicos
 * - Configurar reglas de seguridad globales
 * - Establecer controles de acceso granulares
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Visit::class => VisitPolicy::class,
        Visitor::class => VisitorPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->defineGates();
        $this->defineSystemGates();
        $this->defineReportGates();
        $this->defineAdministrativeGates();
    }

    /**
     * Define authorization gates for specific system actions
     */
    protected function defineGates(): void
    {
        // Gate para acceso general al sistema
        Gate::define('access-system', function (User $user) {
            return $user->canAccessApp();
        });

        // Gate para funciones de administración
        Gate::define('admin-functions', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para funciones de asistente administrativo
        Gate::define('assistant-functions', function (User $user) {
            return $user->isAsistAdm() || $user->isAdmin();
        });

        // Gate para funciones de guardia
        Gate::define('guard-functions', function (User $user) {
            return $user->isGuardia() || $user->isAsistAdm() || $user->isAdmin();
        });

        // Gate para ver información sensible
        Gate::define('view-sensitive-data', function (User $user) {
            return $user->isAdmin() || $user->isAsistAdm() || $user->isGuardia();
        });

        // Gate para operaciones de emergencia
        Gate::define('emergency-operations', function (User $user) {
            return $user->isAdmin() || $user->isGuardia();
        });
    }

    /**
     * Define system-level authorization gates
     */
    protected function defineSystemGates(): void
    {
        // Gate para gestión de usuarios
        Gate::define('manage-users', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para configuración del sistema
        Gate::define('system-configuration', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para mantenimiento del sistema
        Gate::define('system-maintenance', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para backup y restauración
        Gate::define('backup-restore', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para logs del sistema
        Gate::define('view-system-logs', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para métricas y monitoreo
        Gate::define('view-metrics', function (User $user) {
            return $user->isAdmin() || $user->isAsistAdm();
        });

        // Gate para limpiar cache
        Gate::define('clear-cache', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para ejecutar comandos Artisan
        Gate::define('execute-commands', function (User $user) {
            return $user->isAdmin();
        });
    }

    /**
     * Define reporting authorization gates
     */
    protected function defineReportGates(): void
    {
        // Gate para generar reportes básicos (todos los roles activos)
        Gate::define('generate-basic-reports', function (User $user) {
            return $user->isAdmin() || $user->isAsistAdm() || $user->isGuardia() || $user->isAuxUgc();
        });

        // Gate para generar reportes avanzados
        Gate::define('generate-advanced-reports', function (User $user) {
            return $user->isAdmin() || $user->isAsistAdm();
        });

        // Gate para exportar datos
        Gate::define('export-data', function (User $user) {
            return $user->isAdmin() || $user->isAsistAdm();
        });

        // Gate para reportes en tiempo real
        Gate::define('real-time-reports', function (User $user) {
            return $user->isAdmin() || $user->isAsistAdm() || $user->isGuardia();
        });

        // Gate para reportes históricos
        Gate::define('historical-reports', function (User $user) {
            return $user->isAdmin() || $user->isAsistAdm();
        });

        // Gate para reportes de auditoría
        Gate::define('audit-reports', function (User $user) {
            return $user->isAdmin();
        });
    }

    /**
     * Define administrative authorization gates
     */
    protected function defineAdministrativeGates(): void
    {
        // Gate para gestión de roles
        Gate::define('manage-roles', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para asignación de permisos
        Gate::define('assign-permissions', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para configurar notificaciones
        Gate::define('configure-notifications', function (User $user) {
            return $user->isAdmin() || $user->isAsistAdm();
        });

        // Gate para gestión de templates
        Gate::define('manage-templates', function (User $user) {
            return $user->isAdmin() || $user->isAsistAdm();
        });

        // Gate para configurar horarios
        Gate::define('configure-schedules', function (User $user) {
            return $user->isAdmin() || $user->isAsistAdm();
        });

        // Gate para gestión de carnets
        Gate::define('manage-carnets', function (User $user) {
            return $user->isGuardia() || $user->isAsistAdm() || $user->isAdmin();
        });

        // Gate para override de restricciones
        Gate::define('override-restrictions', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para acceso fuera de horario
        Gate::define('after-hours-access', function (User $user) {
            return $user->isAdmin() || $user->isGuardia();
        });

        // Gate para operaciones bulk
        Gate::define('bulk-operations', function (User $user) {
            return $user->isAdmin();
        });

        // Gate para integración con servicios externos
        Gate::define('external-integrations', function (User $user) {
            return $user->isAdmin();
        });
    }

    /**
     * Define context-aware gates that require additional parameters
     */
    protected function defineContextGates(): void
    {
        // Gate para acciones en horario laboral
        Gate::define('business-hours-action', function (User $user, string $action) {
            $isBusinessHours = now()->hour >= 8 && now()->hour <= 18;
            
            // Admins y guardias pueden actuar fuera de horario
            if ($user->isAdmin() || $user->isGuardia()) {
                return true;
            }

            // Otros usuarios solo en horario laboral
            return $isBusinessHours;
        });

        // Gate para límites de visitas por usuario
        Gate::define('visit-creation-limit', function (User $user) {
            $dailyLimit = match ($user->role->name) {
                'Admin' => 1000,
                'Asist_adm' => 500,
                'Guardia' => 200,
                default => 50
            };

            $todaysVisits = Visit::where('user_id', $user->id)
                ->whereDate('created_at', now()->toDateString())
                ->count();

            return $todaysVisits < $dailyLimit;
        });

        // Gate para acceso a ubicaciones específicas
        Gate::define('location-access', function (User $user, string $location) {
            // Lógica específica por ubicación si es necesario
            return $user->canAccessApp();
        });

        // Gate para aprobaciones de emergencia
        Gate::define('emergency-approval', function (User $user, string $reason) {
            if ($user->isAdmin()) {
                return true;
            }

            // Guardias pueden aprobar ciertas emergencias
            if ($user->isGuardia()) {
                $allowedReasons = ['medical', 'security', 'maintenance'];
                return in_array($reason, $allowedReasons);
            }

            return false;
        });
    }
}