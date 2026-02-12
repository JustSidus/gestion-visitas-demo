<?php

namespace App\Services;

use App\Models\Visit;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Servicio para construcción de templates de email
 * 
 * Responsabilidades:
 * - Generar HTML de emails de forma consistente
 * - Formatear datos para templates
 * - Mantener configuración de branding
 * - Separar lógica de presentación
 */
class TemplateService
{
    /**
     * Construye el template HTML para notificación de visita
     */
    public function buildVisitNotificationTemplate(Visit $visit): string
    {
        $data = $this->prepareTemplateData($visit);
        return $this->renderVisitTemplate($data);
    }

    /**
     * Prepara los datos necesarios para el template
     */
    private function prepareTemplateData(Visit $visit): array
    {
        $user = JWTAuth::parseToken()->authenticate();
        
        $visitorsNames = $visit->visitors->map(function($visitor) {
            return $visitor->name . ' ' . $visitor->lastName;
        })->join(', ');
        
        return [
            'visit' => $visit,
            'visitors_names' => $visitorsNames,
            'visit_date' => Carbon::parse($visit->created_at)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'),
            'visit_time' => Carbon::parse($visit->created_at)->format('h:i A'),
            'user_name' => $user->name,
            'app_url' => config('app.url'),
            'logo_url' => $this->getLogoUrl(),
            'colors' => $this->getBrandColors()
        ];
    }

    /**
     * Renderiza el template HTML completo
     */
    private function renderVisitTemplate(array $data): string
    {
        extract($data);
        $colors = $data['colors'];
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Visita Registrada - Institución Demo</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: {$colors['background']};">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: {$colors['background']};">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <!-- Contenedor principal -->
                <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px -2px rgba(30, 78, 121, 0.15);">
                    
                    <!-- Header con logo -->
                    <tr>
                        <td style="background-color: {$colors['primary']}; padding: 32px 40px; text-align: center;">
                            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="text-align: center;">
                                        <div style="background-color: #ffffff; padding: 16px 24px; border-radius: 8px; display: inline-block; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                            <img src="{$logo_url}" alt="Institución Demo" style="max-width: 200px; height: auto; display: block;" onerror="this.onerror=null; this.parentElement.innerHTML='<span style=&quot;font-size: 24px; font-weight: 700; color: {$colors['primary']}; letter-spacing: 2px;&quot;>Institución Demo</span>';" />
                                        </div>
                                        <h1 style="margin: 0; color: #ffffff; font-size: 18px; font-weight: 600; letter-spacing: 0.5px; line-height: 1.4;">
                                            Institución Demo
                                        </h1>
                                        <p style="margin: 8px 0 0; color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 400;">
                                            República Dominicana
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Badge de notificación -->
                    <tr>
                        <td style="background: linear-gradient(to bottom, {$colors['primary']}, {$colors['primary_light']}); padding: 0 40px 24px; text-align: center;">
                            <div style="background-color: #ffffff; border: 2px solid rgba(255,255,255,0.3); padding: 12px 24px; border-radius: 8px; display: inline-block; margin-top: -16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                <span style="color: {$colors['primary']}; font-size: 14px; font-weight: 700; letter-spacing: 0.5px;">
                                    NUEVA VISITA REGISTRADA
                                </span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 32px 40px;">
                            <p style="margin: 0 0 8px; font-size: 15px; color: {$colors['text_primary']}; line-height: 1.6; font-weight: 600;">
                                Estimado(a) <span style="color: {$colors['primary']};">{$visit->namePersonToVisit}</span>,
                            </p>
                            <p style="margin: 0; font-size: 14px; color: {$colors['text_secondary']}; line-height: 1.7;">
                                Se le informa que tiene una visita registrada en la recepción de nuestras instalaciones.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Tabla de información -->
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            {$this->buildVisitDetailsTable($visit, $visitors_names, $visit_date, $visit_time, $colors)}
                        </td>
                    </tr>
                    
                    <!-- Mensaje de acción -->
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            <div style="background: linear-gradient(to right, #E8F0F7, #F1F8E9); border-left: 4px solid {$colors['secondary']}; padding: 16px 20px; border-radius: 6px;">
                                <p style="margin: 0; color: {$colors['text_primary']}; font-size: 13px; line-height: 1.6; font-weight: 500;">
                                    <strong style="color: {$colors['primary']};">Instrucciones:</strong> El visitante se encuentra registrado en recepción. Por favor, diríjase a la entrada principal para recibirlo.
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: {$colors['background']}; padding: 32px 40px;">
                            {$this->buildEmailFooter($user_name, $colors)}
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Construye la tabla de detalles de la visita
     */
    private function buildVisitDetailsTable($visit, $visitorsNames, $visitDate, $visitTime, $colors): string
    {
        return <<<HTML
<table role="presentation" style="width: 100%; border-collapse: collapse; background-color: {$colors['background']}; border: 2px solid {$colors['border']}; border-radius: 10px; overflow: hidden;">
    <!-- Header de la tabla -->
    <tr>
        <td colspan="2" style="background-color: {$colors['primary']}; padding: 14px 20px; border-bottom: 2px solid {$colors['border']};">
            <h2 style="margin: 0; color: #ffffff; font-size: 15px; font-weight: 600; letter-spacing: 0.3px;">
                Detalles de la Visita
            </h2>
        </td>
    </tr>
    
    <!-- Filas de datos -->
    <tr style="background-color: #ffffff;">
        <td style="padding: 16px 20px; border-bottom: 1px solid {$colors['border']}; width: 40%;">
            <span style="color: {$colors['text_secondary']}; font-size: 13px; font-weight: 500;">Visitante(s)</span>
        </td>
        <td style="padding: 16px 20px; border-bottom: 1px solid {$colors['border']}; text-align: right;">
            <span style="color: {$colors['text_primary']}; font-size: 13px; font-weight: 600;">{$visitorsNames}</span>
        </td>
    </tr>
    
    <tr style="background-color: {$colors['background']};">
        <td style="padding: 16px 20px; border-bottom: 1px solid {$colors['border']};">
            <span style="color: {$colors['text_secondary']}; font-size: 13px; font-weight: 500;">Fecha</span>
        </td>
        <td style="padding: 16px 20px; border-bottom: 1px solid {$colors['border']}; text-align: right;">
            <span style="color: {$colors['text_primary']}; font-size: 13px; font-weight: 600;">{$visitDate}</span>
        </td>
    </tr>
    
    <tr style="background-color: #ffffff;">
        <td style="padding: 16px 20px; border-bottom: 1px solid {$colors['border']};">
            <span style="color: {$colors['text_secondary']}; font-size: 13px; font-weight: 500;">Hora de Registro</span>
        </td>
        <td style="padding: 16px 20px; border-bottom: 1px solid {$colors['border']}; text-align: right;">
            <span style="color: {$colors['text_primary']}; font-size: 13px; font-weight: 600;">{$visitTime}</span>
        </td>
    </tr>
    
    <tr style="background-color: {$colors['background']};">
        <td style="padding: 16px 20px; border-bottom: 1px solid {$colors['border']};">
            <span style="color: {$colors['text_secondary']}; font-size: 13px; font-weight: 500;">Motivo de Visita</span>
        </td>
        <td style="padding: 16px 20px; border-bottom: 1px solid {$colors['border']}; text-align: right;">
            <span style="color: {$colors['text_primary']}; font-size: 13px; font-weight: 600;">{$visit->reason}</span>
        </td>
    </tr>
    
    <tr style="background-color: #ffffff;">
        <td style="padding: 16px 20px; border-bottom: 1px solid {$colors['border']};">
            <span style="color: {$colors['text_secondary']}; font-size: 13px; font-weight: 500;">Departamento</span>
        </td>
        <td style="padding: 16px 20px; border-bottom: 1px solid {$colors['border']}; text-align: right;">
            <span style="color: {$colors['text_primary']}; font-size: 13px; font-weight: 600;">{$visit->department}</span>
        </td>
    </tr>
    
    <tr style="background-color: {$colors['background']};">
        <td style="padding: 16px 20px;">
            <span style="color: {$colors['text_secondary']}; font-size: 13px; font-weight: 500;">Carnet Asignado</span>
        </td>
        <td style="padding: 16px 20px; text-align: right;">
            <div style="background: linear-gradient(135deg, {$colors['secondary']}, #7CB342); color: #000000; padding: 6px 16px; border-radius: 6px; display: inline-block; font-weight: 700; font-size: 15px; letter-spacing: 1px; box-shadow: 0 2px 8px rgba(139, 195, 74, 0.3);">
                #{$visit->assigned_carnet}
            </div>
        </td>
    </tr>
</table>
HTML;
    }

    /**
     * Construye el footer del email
     */
    private function buildEmailFooter($userName, $colors): string
    {
        return <<<HTML
<table role="presentation" style="width: 100%; border-collapse: collapse;">
    <!-- Información del registro -->
    <tr>
        <td style="text-align: center; padding-bottom: 24px;">
            <p style="margin: 0; font-size: 12px; color: {$colors['text_secondary']};">
                <strong style="color: {$colors['text_primary']};">Registrado por:</strong> {$userName}
            </p>
            <p style="margin: 4px 0 0; font-size: 11px; color: {$colors['text_secondary']};">
                Sistema de Gestión de Visitas
            </p>
        </td>
    </tr>
    
    <!-- Información de contacto -->
    <tr>
        <td style="padding: 24px 0; border-top: 1px solid {$colors['border']};">
            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%; padding: 8px 0; vertical-align: top;">
                        <p style="margin: 0; font-size: 11px; color: {$colors['text_secondary']}; line-height: 1.6;">
                            <strong style="color: {$colors['primary']}; display: block; margin-bottom: 4px;">Dirección</strong>
                            Av. Demo #123<br>
                            Centro, Ciudad Demo<br>
                            República Dominicana
                        </p>
                    </td>
                    <td style="width: 50%; padding: 8px 0; vertical-align: top; text-align: right;">
                        <p style="margin: 0; font-size: 11px; color: {$colors['text_secondary']}; line-height: 1.6;">
                            <strong style="color: {$colors['primary']}; display: block; margin-bottom: 4px;">Contacto</strong>
                            Tel: 000-000-0000<br>
                            Email: info@demo.example.org<br>
                            Web: www.demo.example.org
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    
    <!-- Horario y copyright -->
    <tr>
        <td style="text-align: center; padding: 16px 0 0; border-top: 1px solid {$colors['border']};">
            <p style="margin: 0; font-size: 11px; color: {$colors['text_secondary']};">
                <strong style="color: {$colors['primary']};">Horario de Atención:</strong> Lunes a Viernes, 8:00 AM - 4:00 PM
            </p>
        </td>
    </tr>
    
    <tr>
        <td style="text-align: center; padding: 20px 0 0; border-top: 1px solid {$colors['border']};">
            <div style="background-color: {$colors['primary']}; color: #ffffff; padding: 8px 20px; border-radius: 6px; display: inline-block; margin-bottom: 12px;">
                <span style="font-size: 10px; font-weight: 600; letter-spacing: 0.8px;">
                    SISTEMA OFICIAL - INSTITUCIÓN DEMO
                </span>
            </div>
            <p style="margin: 0; font-size: 10px; color: {$colors['text_secondary']}; line-height: 1.5;">
                Este es un mensaje automático generado por el Sistema de Gestión de Visitas.<br>
                Por favor, no responda directamente a este correo.
            </p>
            <p style="margin: 12px 0 0; font-size: 10px; color: {$colors['text_secondary']};">
                &copy; 2025 Institución Demo. República Dominicana. Todos los derechos reservados.
            </p>
        </td>
    </tr>
</table>
HTML;
    }

    /**
     * Obtiene la URL del logo
     */
    private function getLogoUrl(): string
    {
        return config('mail.logo_url', 'https://example.org/assets/logo-institucion-demo.png');
    }

    /**
     * Obtiene los colores del branding
     */
    private function getBrandColors(): array
    {
        return [
            'primary' => '#1E4E79',
            'primary_light' => '#2C64B7',
            'secondary' => '#8BC34A',
            'background' => '#F8FAFB',
            'border' => '#E5E7EB',
            'text_primary' => '#1F2937',
            'text_secondary' => '#6B7280'
        ];
    }
}