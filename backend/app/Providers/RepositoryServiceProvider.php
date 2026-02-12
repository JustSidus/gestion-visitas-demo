<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\VisitRepositoryInterface;
use App\Repositories\Eloquent\VisitRepository;
use App\Repositories\Contracts\VisitorRepositoryInterface;
use App\Repositories\Eloquent\VisitorRepository;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;

/**
 * Service Provider para registrar repositorios
 * 
 * Este provider maneja la inyección de dependencias para el Repository Pattern,
 * permitiendo que Laravel resuelva automáticamente las interfaces con sus
 * implementaciones concretas.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios en el contenedor de dependencias
     */
    public function register(): void
    {
        // Bind de repositorios con sus interfaces
        $this->app->bind(VisitRepositoryInterface::class, VisitRepository::class);
        $this->app->bind(VisitorRepositoryInterface::class, VisitorRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    /**
     * Bootstrap de servicios (ejecutado después del register)
     */
    public function boot(): void
    {
        //
    }
}