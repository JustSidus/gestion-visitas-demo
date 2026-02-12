<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato para el repositorio de usuarios
 */
interface UserRepositoryInterface
{
    /**
     * Obtiene todos los usuarios
     * 
     * @return Collection
     */
    public function getAll(): Collection;

    /**
     * Encuentra un usuario por ID
     * 
     * @param int $id ID del usuario
     * @return User|null
     */
    public function findById(int $id): ?User;

    /**
     * Encuentra un usuario por email
     * 
     * @param string $email Email del usuario
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Obtiene usuarios con roles específicos
     * 
     * @param array $roles Array de nombres de roles
     * @return Collection
     */
    public function getUsersByRoles(array $roles): Collection;

    /**
     * Crea un nuevo usuario
     * 
     * @param array $data Datos del usuario
     * @return User
     */
    public function create(array $data): User;

    /**
     * Actualiza un usuario existente
     * 
     * @param int $id ID del usuario
     * @param array $data Datos a actualizar
     * @return User
     */
    public function update(int $id, array $data): User;

    /**
     * Elimina un usuario
     * 
     * @param int $id ID del usuario
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Obtener usuarios activos
     */
    public function getActiveUsers(): array;
}