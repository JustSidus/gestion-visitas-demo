<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupOldJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:cleanup-old
                            {--days=7 : Número de días para mantener failed_jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia failed_jobs antiguos (default: mayores a 7 días)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $this->info(" Iniciando limpieza de failed_jobs antiguos (>{$days} días)...");

        try {
            $cutoffDate = Carbon::now()->subDays($days);

            // Limpiar failed_jobs antiguos
            $deletedFailedJobs = DB::table('failed_jobs')
                ->where('failed_at', '<', $cutoffDate)
                ->delete();

            $this->info(" Se eliminaron {$deletedFailedJobs} failed_jobs antiguos");
            
            // Nota: Los jobs completados se eliminan automáticamente de la tabla 'jobs'
            // Solo persisten en 'failed_jobs' los que fallaron
            
            Log::info('Jobs cleanup completed', [
                'deleted_failed_jobs' => $deletedFailedJobs,
                'retention_days' => $days,
                'cutoff_date' => $cutoffDate->toDateTimeString()
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(' Error al limpiar jobs: ' . $e->getMessage());
            Log::error('Jobs cleanup failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}

