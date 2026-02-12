<?php

namespace App\Jobs;

use App\Models\Visit;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para enviar notificaciones de visitas de forma asíncrona
 * 
 * Responsabilidades:
 * - Enviar emails de notificación de visitas
 * - Manejar errores y reintentos automáticos
 * - Registrar actividad para auditoría
 * - Optimizar el rendimiento del sistema
 */
class SendVisitNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El número de veces que se puede intentar el job.
     */
    public int $tries = 3;

    /**
     * El número de segundos después de los cuales el job puede timeoutear.
     */
    public int $timeout = 120;

    /**
     * Eliminar el job si sus modelos dependientes no existen.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * ID de la visita para la cual enviar la notificación
     */
    private int $visitId;

    /**
     * Tipo de notificación a enviar
     */
    private string $notificationType;

    /**
     * Datos adicionales para la notificación
     */
    private array $additionalData;

    /**
     * Create a new job instance.
     */
    public function __construct(int $visitId, string $notificationType = 'created', array $additionalData = [])
    {
        $this->visitId = $visitId;
        $this->notificationType = $notificationType;
        $this->additionalData = $additionalData;
        
        // Configurar la cola específica para emails
        $this->onQueue('emails');
        
        Log::info("SendVisitNotificationJob queued", [
            'visit_id' => $visitId,
            'notification_type' => $notificationType,
            'queue' => 'emails'
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(EmailService $emailService): void
    {
        try {
            Log::info("Processing SendVisitNotificationJob", [
                'visit_id' => $this->visitId,
                'notification_type' => $this->notificationType,
                'attempt' => $this->attempts()
            ]);

            // Cargar la visita con todas las relaciones necesarias
            $visit = Visit::with(['visitors', 'user', 'visitStatus'])
                ->findOrFail($this->visitId);

            // Verificar que la visita tenga email de destino
            if (empty($visit->person_to_visit_email)) {
                Log::warning("Visit notification skipped - no email provided", [
                    'visit_id' => $this->visitId
                ]);
                return;
            }

            // Determinar el tipo de notificación y enviar
            $success = match($this->notificationType) {
                'created' => $this->sendVisitCreatedNotification($emailService, $visit),
                'closed' => $this->sendVisitClosedNotification($emailService, $visit),
                'reminder' => $this->sendVisitReminderNotification($emailService, $visit),
                'updated' => $this->sendVisitUpdatedNotification($emailService, $visit),
                default => throw new \InvalidArgumentException("Notification type '{$this->notificationType}' not supported")
            };

            if ($success) {
                Log::info("Visit notification sent successfully", [
                    'visit_id' => $this->visitId,
                    'notification_type' => $this->notificationType,
                    'recipient' => $visit->person_to_visit_email
                ]);
            } else {
                throw new \Exception("Failed to send notification");
            }

        } catch (\Exception $e) {
            Log::error("SendVisitNotificationJob failed", [
                'visit_id' => $this->visitId,
                'notification_type' => $this->notificationType,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-lanzar la excepción para activar el sistema de reintentos
            throw $e;
        }
    }

    /**
     * Enviar notificación de visita creada
     */
    private function sendVisitCreatedNotification(EmailService $emailService, Visit $visit): bool
    {
        try {
            $emailService->sendVisitNotification($visit, $visit->person_to_visit_email);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send visit created notification", [
                'visit_id' => $visit->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Enviar notificación de visita cerrada
     */
    private function sendVisitClosedNotification(EmailService $emailService, Visit $visit): bool
    {
        try {
            // Para notificaciones de cierre, podrías necesitar un método específico en EmailService
            // Por ahora, usamos el método existente
            $emailService->sendVisitNotification($visit, $visit->person_to_visit_email);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send visit closed notification", [
                'visit_id' => $visit->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Enviar recordatorio de visita
     */
    private function sendVisitReminderNotification(EmailService $emailService, Visit $visit): bool
    {
        try {
            // Para recordatorios, usar el método existente o crear uno específico
            $emailService->sendVisitNotification($visit, $visit->person_to_visit_email);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send visit reminder notification", [
                'visit_id' => $visit->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Enviar notificación de visita actualizada
     */
    private function sendVisitUpdatedNotification(EmailService $emailService, Visit $visit): bool
    {
        try {
            // Para actualizaciones, usar el método existente
            $emailService->sendVisitNotification($visit, $visit->person_to_visit_email);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send visit updated notification", [
                'visit_id' => $visit->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendVisitNotificationJob permanently failed", [
            'visit_id' => $this->visitId,
            'notification_type' => $this->notificationType,
            'final_error' => $exception->getMessage(),
            'attempts_made' => $this->attempts()
        ]);

        // Opcionalmente, notificar a los administradores del fallo
        // Esto podría ser otro Job o una notificación directa
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Reintentar después de 30 segundos, luego 60 segundos
        return [30, 60];
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'visit:' . $this->visitId,
            'notification:' . $this->notificationType,
            'email'
        ];
    }
}