<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Implementación Eloquent del repositorio de usuarios
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * Obtiene todos los usuarios
     */
    public function getAll(): Collection
    {
        return User::with('roles')->orderBy('name')->get();
    }

    /**
     * Encuentra un usuario por ID
     */
    public function findById(int $id): ?User
    {
        return User::with('roles')->find($id);
    }

    /**
     * Encuentra un usuario por email
     */
    public function findByEmail(string $email): ?User
    {
        return User::with('roles')->where('email', $email)->first();
    }

    /**
     * Obtiene usuarios con roles específicos
     */
    public function getUsersByRoles(array $roles): Collection
    {
        return User::with('roles')
            ->whereHas('roles', function ($query) use ($roles) {
                $query->whereIn('name', $roles);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Crea un nuevo usuario
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Actualiza un usuario existente
     */
    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user->fresh();
    }

    /**
     * Elimina un usuario
     */
    public function delete(int $id): bool
    {
        $user = User::findOrFail($id);
        return $user->delete();
    }

    /**
     * Obtener usuarios activos
     */
    public function getActiveUsers(): array
    {
        return User::with('roles')
            ->where('is_active', true)
            ->withCount(['createdVisits', 'closedVisits'])
            ->orderBy('name')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'visits_created_count' => $user->created_visits_count ?? 0,
                    'visits_closed_count' => $user->closed_visits_count ?? 0,
                    'last_activity' => $user->updated_at?->diffForHumans()
                ];
            })
            ->toArray();
    }
}