<?php

namespace App\Policies;

use App\Models\Visit;
use App\Models\User;
use App\Enums\EnumVisitStatuses;

/**
 * Policy para controlar acceso a visitas
 * 
 * Responsabilidades:
 * - Definir permisos granulares para operaciones con visitas
 * - Implementar lógica de autorización basada en roles
 * - Controlar acceso a funciones específicas según contexto
 * - Proporcionar base para auditoría de permisos
 */
class VisitPolicy
{
    /**
     * Determine whether the user can view any visits.
     */
    public function viewAny(User $user): bool
    {
        // Todos los usuarios autenticados pueden ver visitas
        // Pero el alcance se limita en el Repository según el rol
        return $user->canAccessApp();
    }

    /**
     * Determine whether the user can view the visit.
     */
    public function view(User $user, Visit $visit): bool
    {
        // Admins y Asist_adm pueden ver todas las visitas
        if ($user->isAdmin() || $user->isAsistAdm()) {
            return true;
        }

        // Guardias pueden ver todas las visitas
        if ($user->isGuardia()) {
            return true;
        }

        // Otros usuarios solo pueden ver visitas que crearon
        return $user->id === $visit->user_id;
    }

    /**
     * Determine whether the user can create visits.
     */
    public function create(User $user): bool
    {
        // Solo Admins y Asist_adm pueden crear visitas
        return $user->isAdmin() || $user->isAsistAdm();
    }

    /**
     * Determine whether the user can update the visit.
     */
    public function update(User $user, Visit $visit): bool
    {
        // Solo Admins pueden actualizar visitas
        if ($user->isAdmin()) {
            return true;
        }

        // Asist_adm puede actualizar solo visitas activas que no han sido cerradas
        if ($user->isAsistAdm()) {
            return $visit->status_id === EnumVisitStatuses::ABIERTO->value && is_null($visit->end_at);
        }

        return false;
    }

    /**
     * Determine whether the user can close the visit.
     * 
     * PUNTO CIEGO #5: Valida que usuario esté activo y respeta horarios
     */
    public function close(User $user, Visit $visit): bool
    {
        // Validar que usuario está activo
        if (!$user->is_active) {
            return false;
        }
        
        // Solo visitas abiertas pueden ser cerradas
        if ($visit->status_id !== EnumVisitStatuses::ABIERTO->value || $visit->end_at) {
            return false;
        }

        // Regla de roles: Admin, Asist_adm, Guardia y aux_ugc
        if (!($user->isAdmin() || $user->isAsistAdm() || $user->isGuardia() || $user->isAuxUgc())) {
            return false;
        }
        
        // PUNTO CIEGO #5: aux_ugc tiene restricción horaria (07:00-19:00)
        if ($user->isAuxUgc()) {
            $hour = now()->hour;
            if ($hour < 7 || $hour >= 19) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Determine whether the user can delete the visit.
     */
    public function delete(User $user, Visit $visit): bool
    {
        // Solo Admins pueden eliminar visitas
        if (!$user->isAdmin()) {
            return false;
        }

        // No se pueden eliminar visitas activas (status abierto)
        return $visit->status_id === EnumVisitStatuses::CERRADO->value;
    }

    /**
     * Determine whether the user can restore the visit.
     */
    public function restore(User $user, Visit $visit): bool
    {
        // Solo Admins pueden restaurar visitas
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the visit.
     */
    public function forceDelete(User $user, Visit $visit): bool
    {
        // Solo Admins pueden eliminar permanentemente
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can export visits data.
     */
    public function export(User $user): bool
    {
        // Admins y Asist_adm pueden exportar datos
        return $user->isAdmin() || $user->isAsistAdm();
    }

    /**
     * Determine whether the user can view visit statistics.
     */
    public function viewStatistics(User $user): bool
    {
        // Admin, Asist_adm, Guardia y aux_ugc pueden ver estadísticas
        // (aux_ugc consume métricas de su dominio misional desde frontend)
        return $user->isAdmin() || $user->isAsistAdm() || $user->isGuardia() || $user->isAuxUgc();
    }

    /**
     * Determine whether the user can send notifications for the visit.
     */
    public function sendNotification(User $user, Visit $visit): bool
    {
        // Solo quien puede ver la visita puede enviar notificaciones
        return $this->view($user, $visit);
    }

    /**
     * Determine whether the user can assign carnets.
     */
    public function assignCarnet(User $user): bool
    {
        // Solo Guardias, Asist_adm y Admins pueden asignar carnets
        return $user->isGuardia() || $user->isAsistAdm() || $user->isAdmin();
    }

    /**
     * Determine whether the user can manage vehicles in visits.
     */
    public function manageVehicles(User $user): bool
    {
        // Solo Guardias y superiores pueden gestionar vehículos
        return $user->isGuardia() || $user->isAsistAdm() || $user->isAdmin();
    }

    /**
     * Determine whether the user can bulk close visits.
     */
    public function bulkClose(User $user): bool
    {
        // Solo Admins pueden cerrar visitas masivamente
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can access audit logs for visits.
     */
    public function viewAuditLogs(User $user): bool
    {
        // Solo Admins pueden ver logs de auditoría
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can override visit restrictions.
     */
    public function override(User $user): bool
    {
        // Solo Admins pueden override restricciones
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage visit status.
     */
    public function manageStatus(User $user, Visit $visit): bool
    {
        // Admins pueden cambiar cualquier estado
        if ($user->isAdmin()) {
            return true;
        }

        // Asist_adm y Guardias pueden cambiar de abierto a cerrado
        if (($user->isAsistAdm() || $user->isGuardia()) && $visit->status_id === EnumVisitStatuses::ABIERTO->value) {
            return true;
        }

        return false;
    }
}