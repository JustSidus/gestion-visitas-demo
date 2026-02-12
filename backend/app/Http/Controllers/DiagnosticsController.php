<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Controlador de diagnósticos para debugging en producción
 * 
 * SOLO para desarrollo/debugging. Eliminar en producción final.
 */
class DiagnosticsController extends Controller
{
    /**
     * Obtener estado de la aplicación
     */
    public function status()
    {
        $diagnostics = [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
            ],
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'env_variables' => $this->checkEnvVariables(),
            'microsoft_graph' => $this->checkMicrosoftGraph(),
            'jwt' => [
                'ttl' => config('jwt.ttl', 'NOT SET'),
                'secret_set' => !empty(config('jwt.secret')),
            ],
        ];

        return response()->json($diagnostics, 200);
    }

    /**
     * Verificar conexión a BD principal
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection('mysql')->getPdo();
            return [
                'status' => 'OK',
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'message' => 'Conexión exitosa'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'ERROR',
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar BD secundaria (Sistema Externo de Alertas)
     */
    private function checkAlertsDatabase(): array
    {
        try {
            DB::connection('alerts_db')->getPdo();
            return [
                'status' => 'OK',
                'host' => config('database.connections.alerts_db.host'),
                'database' => config('database.connections.alerts_db.database'),
                'message' => 'Conexión exitosa'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'ERROR',
                'host' => config('database.connections.alerts_db.host'),
                'database' => config('database.connections.alerts_db.database'),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar Cache
     */
    private function checkCache(): array
    {
        try {
            Cache::put('test_key', 'test_value', 1);
            $value = Cache::get('test_key');
            Cache::forget('test_key');
            
            return [
                'status' => 'OK',
                'driver' => config('cache.default'),
                'message' => 'Cache funcionando'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'ERROR',
                'driver' => config('cache.default'),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar variables de entorno críticas
     */
    private function checkEnvVariables(): array
    {
        return [
            'APP_KEY' => !empty(env('APP_KEY')) ? ' SET' : ' NOT SET',
            'JWT_SECRET' => !empty(env('JWT_SECRET')) ? ' SET' : ' NOT SET',
            'DB_HOST' => env('DB_HOST', 'NOT SET'),
            'DB_DATABASE' => env('DB_DATABASE', 'NOT SET'),
            'AZURE_CLIENT_ID' => !empty(env('AZURE_CLIENT_ID')) ? ' SET' : ' NOT SET',
            'AZURE_TENANT_ID' => !empty(env('AZURE_TENANT_ID')) ? ' SET' : ' NOT SET',
            'ALERTS_DB_HOST' => env('ALERTS_DB_HOST', 'NOT SET'),
            'ALERTS_DB_DATABASE' => env('ALERTS_DB_DATABASE', 'NOT SET'),
        ];
    }

    /**
     * Verificar Microsoft Graph Service
     */
    private function checkMicrosoftGraph(): array
    {
        return [
            'client_id' => !empty(env('AZURE_CLIENT_ID')) ? ' SET' : ' NOT SET',
            'tenant_id' => !empty(env('AZURE_TENANT_ID')) ? ' SET' : ' NOT SET',
            'client_secret' => !empty(env('AZURE_CLIENT_SECRET')) ? ' SET' : ' NOT SET',
            'redirect_uri' => env('AZURE_REDIRECT_URI', 'NOT SET'),
        ];
    }

    /**
     * Test de BD
     */
    public function testDatabase()
    {
        try {
            $users = DB::connection('mysql')->table('users')->count();
            return response()->json([
                'status' => 'OK',
                'users_count' => $users,
                'message' => 'BD accesible'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Test database failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug - Ver exactamente qué valores de BD se están usando
     */
    public function debugDatabase()
    {
        return response()->json([
            'db_connection' => config('database.default'),
            'mysql_config' => [
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'database' => config('database.connections.mysql.database'),
                'username' => config('database.connections.mysql.username'),
                'charset' => config('database.connections.mysql.charset'),
            ],
            'raw_env' => [
                'DB_HOST' => env('DB_HOST'),
                'DB_PORT' => env('DB_PORT'),
                'DB_DATABASE' => env('DB_DATABASE'),
                'DB_USERNAME' => env('DB_USERNAME'),
            ],
            'env_file_exists' => file_exists(base_path('.env'))
        ], 200);
    }
}

