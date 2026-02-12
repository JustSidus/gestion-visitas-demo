<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use App\Models\Visit;
use App\Models\Visitor;
use App\Models\User;
use App\Observers\VisitObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar LoggerService como singleton
        $this->app->singleton(\App\Services\LoggerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Asegurar que los directorios de caché existen
        $this->ensureCacheDirectoriesExist();
        
        // Registrar observers para logging automático
        Visit::observe(VisitObserver::class);
        
        // Configurar logging de queries en desarrollo
        if (app()->environment('local', 'development')) {
            DB::listen(function ($query) {
                app(\App\Services\LoggerService::class)->database(
                    'query',
                    'unknown',
                    [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time_ms' => $query->time,
                    ]
                );
            });
        }
    }

    /**
     * Asegurar que existen los directorios necesarios para caché
     */
    private function ensureCacheDirectoriesExist(): void
    {
        $directories = [
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/views'),
            storage_path('framework/sessions'),
            storage_path('framework/temp'),
            storage_path('fonts'),
            storage_path('logs'),
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }
}
