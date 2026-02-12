<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupOldAuditLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit-logs:cleanup-old
                            {--days=90 : Número de días para mantener audit_logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia audit_logs antiguos (default: mayores a 90 días)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $this->info(" Iniciando limpieza de audit_logs antiguos (>{$days} días)...");

        try {
            $cutoffDate = Carbon::now()->subDays($days);

            // Contar registros antes de eliminar
            $totalBefore = DB::table('audit_logs')->count();

            // Eliminar audit_logs antiguos
            $deleted = DB::table('audit_logs')
                ->where('created_at', '<', $cutoffDate)
                ->delete();

            $totalAfter = DB::table('audit_logs')->count();

            $this->info(" Se eliminaron {$deleted} audit_logs antiguos");
            $this->info(" Registros antes: {$totalBefore} | Registros después: {$totalAfter}");
            
            Log::info('Audit logs cleanup completed', [
                'deleted_records' => $deleted,
                'retention_days' => $days,
                'cutoff_date' => $cutoffDate->toDateTimeString(),
                'records_before' => $totalBefore,
                'records_after' => $totalAfter
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(' Error al limpiar audit_logs: ' . $e->getMessage());
            Log::error('Audit logs cleanup failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}

