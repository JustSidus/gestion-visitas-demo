<?php

namespace App\Services;

use App\Models\Visit;
use App\Services\TemplateService;
use App\Services\MicrosoftGraphService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Servicio responsable del envío de notificaciones por email
 * 
 * Responsabilidades:
 * - Gestionar tokens de Microsoft Graph
 * - Coordinar el envío de emails
 * - Manejar errores de envío de forma elegante
 * - Logging detallado de operaciones
 * - Validar requisitos antes del envío
 */
class EmailService
{
    protected $graphService;
    protected $templateService;

    public function __construct(
        MicrosoftGraphService $graphService,
        TemplateService $templateService
    ) {
        $this->graphService = $graphService;
        $this->templateService = $templateService;
    }

    /**
     * Envía notificación de nueva visita
     * 
     * @param Visit $visit La visita registrada
     * @param string $recipientEmail Email del destinatario
     * @throws \Exception Si no hay token disponible o falla el envío
     */
    public function sendVisitNotification(Visit $visit, string $recipientEmail): void
    {
        try {
            // 1. Validar prerequisitos
            $this->validateSendRequirements($visit, $recipientEmail);
            
            // 2. Obtener token de Microsoft
            $token = $this->getMicrosoftToken();
            
            // 3. Preparar datos del email
            $emailData = $this->prepareEmailData($visit);
            
            // 4. Enviar email
            $this->graphService->sendMailAsUser(
                $token,
                $recipientEmail,
                $emailData['subject'],
                $emailData['body'],
                [] // Sin CC por ahora
            );
            
            // 5. Log de éxito
            $this->logSuccessfulSend($visit, $recipientEmail);
            
        } catch (\Exception $e) {
            // 6. Log de error y re-lanzar excepción
            $this->logFailedSend($visit, $recipientEmail, $e);
            throw $e;
        }
    }

    /**
     * Valida que se cumplan los requisitos para enviar email
     * 
     * @param Visit $visit
     * @param string $recipientEmail
     * @throws \InvalidArgumentException Si faltan datos requeridos
     */
    private function validateSendRequirements(Visit $visit, string $recipientEmail): void
    {
        if (empty($recipientEmail)) {
            throw new \InvalidArgumentException('Email del destinatario es requerido');
        }

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email del destinatario no es válido');
        }

        if (!$visit->exists) {
            throw new \InvalidArgumentException('La visita debe existir en la base de datos');
        }

        // Cargar visitantes si no están cargados
        if (!$visit->relationLoaded('visitors')) {
            $visit->load('visitors');
        }

        if ($visit->visitors->isEmpty()) {
            throw new \InvalidArgumentException('La visita debe tener al menos un visitante');
        }
    }

    /**
     * Obtiene el token de Microsoft del usuario autenticado
     * 
     * @return string Token de acceso
     * @throws \Exception Si no hay token disponible
     */
    private function getMicrosoftToken(): string
    {
        $user = JWTAuth::parseToken()->authenticate();
        $cacheKey = 'microsoft_token_' . $user->id;
        $encryptedToken = Cache::get($cacheKey);
        
        if (!$encryptedToken) {
            Log::warning('Token de Microsoft no encontrado en caché', [
                'user_id' => $user->id,
                'cache_key' => $cacheKey
            ]);
            
            throw new \Exception('Token de Microsoft no disponible. Por favor, vuelva a autenticarse.');
        }
        
        // Desencriptar el token
        try {
            return Crypt::decryptString($encryptedToken);
        } catch (\Exception $e) {
            Log::error('Error al desencriptar token de Microsoft', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Error al procesar token de Microsoft.');
        }
    }

    /**
     * Prepara los datos del email (asunto y cuerpo)
     * 
     * @param Visit $visit
     * @return array ['subject' => string, 'body' => string]
     */
    private function prepareEmailData(Visit $visit): array
    {
        // Asegurar que los visitantes estén cargados
        if (!$visit->relationLoaded('visitors')) {
            $visit->load('visitors');
        }
        
        $visitorsNames = $visit->visitors->map(function($visitor) {
            return trim($visitor->name . ' ' . $visitor->lastName);
        })->join(', ');
        
        $subject = $this->buildEmailSubject($visitorsNames, $visit);
        $body = $this->templateService->buildVisitNotificationTemplate($visit);
        
        return [
            'subject' => $subject,
            'body' => $body
        ];
    }

    /**
     * Construye el asunto del email
     * 
     * @param string $visitorsNames
     * @param Visit $visit
     * @return string
     */
    private function buildEmailSubject(string $visitorsNames, Visit $visit): string
    {
        if ($visit->mission_case) {
            return 'Nueva Visita - Caso Misional - ' . $visitorsNames;
        }
        
        return 'Nueva Visita Registrada - ' . $visitorsNames;
    }

    /**
     * Registra envío exitoso con contexto detallado
     * 
     * @param Visit $visit
     * @param string $recipient
     */
    private function logSuccessfulSend(Visit $visit, string $recipient): void
    {
        $context = [
            'visit_id' => $visit->id,
            'recipient' => $this->maskEmail($recipient),
            'visitors_count' => $visit->visitors->count(),
            'assigned_carnet' => $visit->assigned_carnet,
            'mission_case' => $visit->mission_case,
            'sent_at' => now()->toISOString(),
            'user_id' => JWTAuth::parseToken()->authenticate()->id
        ];

        Log::info('Email de visita enviado exitosamente', $context);
    }

    /**
     * Registra fallo en envío con contexto detallado
     * 
     * @param Visit $visit
     * @param string $recipient
     * @param \Exception $exception
     */
    private function logFailedSend(Visit $visit, string $recipient, \Exception $exception): void
    {
        $context = [
            'visit_id' => $visit->id,
            'recipient' => $this->maskEmail($recipient),
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'error_file' => $exception->getFile(),
            'error_line' => $exception->getLine(),
            'failed_at' => now()->toISOString(),
            'user_id' => JWTAuth::parseToken()->authenticate()->id
        ];

        Log::error('Error al enviar email de visita', $context);
    }

    /**
     * Enmascara email para logs (protección de privacidad)
     * 
     * @param string $email
     * @return string Email enmascarado
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***invalid***';
        }

        $localPart = $parts[0];
        $domain = $parts[1];

        if (strlen($localPart) <= 2) {
            return str_repeat('*', strlen($localPart)) . '@' . $domain;
        }

        $maskedLocal = substr($localPart, 0, 2) . str_repeat('*', strlen($localPart) - 2);
        return $maskedLocal . '@' . $domain;
    }

    /**
     * Verifica si el servicio de email está disponible
     * 
     * @return bool
     */
    public function isEmailServiceAvailable(): bool
    {
        try {
            $this->getMicrosoftToken();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene estadísticas de envío de emails (para dashboard)
     * 
     * @return array
     */
    public function getEmailStats(): array
    {
        // En futuras versiones, esto podría venir de una tabla de logs de emails
        return [
            'service_available' => $this->isEmailServiceAvailable(),
            'last_check' => now()->toISOString()
        ];
    }
}