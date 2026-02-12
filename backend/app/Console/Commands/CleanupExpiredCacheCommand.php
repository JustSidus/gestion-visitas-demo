<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupExpiredCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:cleanup-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia registros expirados de la tabla cache (database driver)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info(' Iniciando limpieza de cache expirado...');

        try {
            $cacheTable = config('cache.stores.database.table', 'cache');
            $currentTime = time();

            // Eliminar registros expirados (expiration < tiempo actual)
            $deleted = DB::table($cacheTable)
                ->where('expiration', '<', $currentTime)
                ->delete();

            $this->info(" Se eliminaron {$deleted} registros expirados de la tabla '{$cacheTable}'");
            
            Log::info('Cache cleanup completed', [
                'deleted_records' => $deleted,
                'table' => $cacheTable
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(' Error al limpiar cache: ' . $e->getMessage());
            Log::error('Cache cleanup failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}

