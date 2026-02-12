<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visitor;
use App\Enums\EnumVisitStatuses;

/**
 * Policy para controlar acceso a visitantes
 * 
 * Responsabilidades:
 * - Definir permisos para operaciones con visitantes
 * - Controlar acceso basado en roles y contexto
 * - Manejar autorización para datos sensibles
 * - Proporcionar base para auditoría
 */
class VisitorPolicy
{
    /**
     * Determine whether the user can view any visitors.
     */
    public function viewAny(User $user): bool
    {
        // Todos los usuarios autenticados pueden ver visitantes
        return $user->canAccessApp();
    }

    /**
     * Determine whether the user can view the visitor.
     */
    public function view(User $user, Visitor $visitor): bool
    {
        // Todos los usuarios autenticados pueden ver visitantes
        // (los datos sensibles se filtran en el Resource)
        return $user->canAccessApp();
    }

    /**
     * Determine whether the user can view sensitive visitor data.
     */
    public function viewSensitiveData(User $user, Visitor $visitor): bool
    {
        // Solo Admins, Asist_adm y Guardias pueden ver datos sensibles
        return $user->isAdmin() || $user->isAsistAdm() || $user->isGuardia();
    }

    /**
     * Determine whether the user can create visitors.
     */
    public function create(User $user): bool
    {
        // Admins, Asist_adm y Guardias pueden crear visitantes
        return $user->isAdmin() || $user->isAsistAdm() || $user->isGuardia();
    }

    /**
     * Determine whether the user can update the visitor.
     */
    public function update(User $user, Visitor $visitor): bool
    {
        // Solo Admins y Asist_adm pueden actualizar visitantes
        return $user->isAdmin() || $user->isAsistAdm();
    }

    /**
     * Determine whether the user can delete the visitor.
     */
    public function delete(User $user, Visitor $visitor): bool
    {
        // Solo Admins pueden eliminar visitantes
        if (!$user->isAdmin()) {
            return false;
        }

        // No se puede eliminar visitante con visitas activas
        $hasActiveVisits = $visitor->visits()
            ->where('status_id', EnumVisitStatuses::ABIERTO->value)
            ->exists();

        return !$hasActiveVisits;
    }

    /**
     * Determine whether the user can restore the visitor.
     */
    public function restore(User $user, Visitor $visitor): bool
    {
        // Solo Admins pueden restaurar visitantes
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the visitor.
     */
    public function forceDelete(User $user, Visitor $visitor): bool
    {
        // Solo Admins pueden eliminar permanentemente
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can export visitor data.
     */
    public function export(User $user): bool
    {
        // Solo Admins y Asist_adm pueden exportar datos de visitantes
        return $user->isAdmin() || $user->isAsistAdm();
    }

    /**
     * Determine whether the user can view visitor statistics.
     */
    public function viewStatistics(User $user): bool
    {
        // Admin, Asist_adm y Guardia pueden ver estadísticas
        // aux_ugc NO gestiona visitantes directamente
        return $user->isAdmin() || $user->isAsistAdm() || $user->isGuardia();
    }

    /**
     * Determine whether the user can search visitors by document.
     */
    public function searchByDocument(User $user): bool
    {
        // Admins, Asist_adm y Guardias pueden buscar por documento
        return $user->isAdmin() || $user->isAsistAdm() || $user->isGuardia();
    }

    /**
     * Determine whether the user can manage visitor photos.
     */
    public function managePhotos(User $user): bool
    {
        // Solo Guardias y superiores pueden gestionar fotos
        return $user->isGuardia() || $user->isAsistAdm() || $user->isAdmin();
    }

    /**
     * Determine whether the user can blacklist visitors.
     */
    public function blacklist(User $user): bool
    {
        // Solo Admins pueden poner en lista negra
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view visitor history.
     */
    public function viewHistory(User $user, Visitor $visitor): bool
    {
        // Todos los usuarios autenticados pueden ver historial básico
        // Los detalles sensibles se filtran según otros permisos
        return $user->canAccessApp();
    }

    /**
     * Determine whether the user can merge duplicate visitors.
     */
    public function merge(User $user): bool
    {
        // Solo Admins pueden fusionar visitantes duplicados
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can bulk update visitors.
     */
    public function bulkUpdate(User $user): bool
    {
        // Solo Admins pueden actualizar masivamente
        return $user->isAdmin();
    }
}