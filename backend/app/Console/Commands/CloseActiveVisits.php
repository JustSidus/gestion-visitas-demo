<?php

namespace App\Console\Commands;

use App\Enums\EnumVisitStatuses;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseActiveVisits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visits:close-active {--dry-run : Ejecutar sin hacer cambios para ver qué se cerraría}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cierra automáticamente todas las visitas activas a las 11:59 PM';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info(' MODO PRUEBA - No se realizarán cambios en la base de datos');
        }

        $this->info(' Buscando visitas activas...');

        // Buscar todas las visitas activas
        $activeVisits = Visit::with('visitors')
            ->where('status_id', EnumVisitStatuses::ABIERTO->value)
            ->get();

        if ($activeVisits->isEmpty()) {
            $this->info(' No hay visitas activas para cerrar.');
            Log::info('Comando CloseActiveVisits ejecutado: No hay visitas activas');
            return;
        }

        $this->info(" Encontradas {$activeVisits->count()} visitas activas:");

        // Mostrar detalles de las visitas que se cerrarán
        foreach ($activeVisits as $visit) {
            $visitorName = $visit->visitors->first()?->name . ' ' . $visit->visitors->first()?->lastName ?? 'Sin visitante';
            $this->line("  - ID {$visit->id}: {$visitorName} - Iniciada: {$visit->created_at->format('d/m/Y H:i')}");
        }

        if ($isDryRun) {
            $this->info(" Modo prueba completado. Se cerrarían {$activeVisits->count()} visitas.");
            return;
        }

        // Confirmar antes de proceder (solo en producción)
        if (!$this->confirm("¿Está seguro de cerrar {$activeVisits->count()} visitas activas?", true)) {
            $this->info(' Operación cancelada por el usuario.');
            return;
        }

        $this->info(' Cerrando visitas...');

        $closedCount = 0;
        $errors = [];

        foreach ($activeVisits as $visit) {
            try {
                // Cerrar la visita
                $visit->status_id = EnumVisitStatuses::CERRADO->value;
                $visit->end_at = Carbon::now();
                $visit->closed_by = null; // Cerrado automáticamente por el sistema
                $visit->save();

                $closedCount++;

                // Log detallado
                $visitorInfo = $visit->visitors->first() ?
                    $visit->visitors->first()->name . ' ' . $visit->visitors->first()->lastName :
                    'Sin visitante';

                Log::info('Visita cerrada automáticamente', [
                    'visit_id' => $visit->id,
                    'visitor' => $visitorInfo,
                    'started_at' => $visit->created_at,
                    'closed_at' => $visit->end_at,
                    'carnet' => $visit->assigned_carnet
                ]);

            } catch (\Exception $e) {
                $errors[] = "Error cerrando visita ID {$visit->id}: {$e->getMessage()}";
                Log::error('Error cerrando visita automáticamente', [
                    'visit_id' => $visit->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Resultado final
        if ($closedCount > 0) {
            $this->info(" {$closedCount} visitas cerradas exitosamente.");
        }

        if (!empty($errors)) {
            $this->error(' Errores encontrados:');
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }

        // Log resumen
        Log::info('Comando CloseActiveVisits completado', [
            'total_active_visits' => $activeVisits->count(),
            'closed_successfully' => $closedCount,
            'errors_count' => count($errors),
            'executed_at' => Carbon::now()
        ]);

        $this->info(' Proceso completado.');
    }
}

