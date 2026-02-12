<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para interactuar con Microsoft Graph API
 * 
 * Este servicio maneja:
 * - Validación de tokens de acceso
 * - Búsqueda de usuarios en Azure AD
 * - Envío de correos electrónicos
 * - Obtención de datos de usuarios
 */
class MicrosoftGraphService
{
    private Client $client;
    private string $baseUrl = 'https://graph.microsoft.com/v1.0';

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => true, // Verificar SSL en producción
        ]);
    }

    /**
     * Valida el token de acceso y obtiene datos del usuario autenticado
     * 
     * @param string $accessToken Token de acceso de Microsoft
     * @return array|null Datos del usuario o null si el token es inválido
     */
    public function validateToken(string $accessToken): ?array
    {
        $startTime = microtime(true);
        
        try {
            $response = $this->client->get($this->baseUrl . '/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);

            $userData = json_decode($response->getBody()->getContents(), true);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Token de Microsoft validado', [
                'user' => $userData['mail'] ?? $userData['userPrincipalName'],
                'graph_response_ms' => $duration,
                'slow_request' => $duration > 2000 // Marcar si tardó más de 2 segundos
            ]);

            return $userData;

        } catch (GuzzleException $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error('Error validando token de Microsoft', [
                'error' => $e->getMessage(),
                'status' => $e->getCode(),
                'graph_response_ms' => $duration,
                'timeout' => $duration > 25000 // Timeout configurado en 30s
            ]);
            return null;
        }
    }

    /**
     * Busca un usuario por su correo electrónico en Azure AD
     * 
     * @param string $accessToken Token de acceso
     * @param string $email Correo electrónico del usuario
     * @return array|null Datos del usuario o null si no se encuentra
     */
    public function getUserByEmail(string $accessToken, string $email): ?array
    {
        try {
            $response = $this->client->get(
                $this->baseUrl . '/users/' . urlencode($email),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ]
                ]
            );

            return json_decode($response->getBody()->getContents(), true);

        } catch (GuzzleException $e) {
            Log::warning('Usuario no encontrado en Azure AD', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Busca usuarios en Azure AD por nombre o correo
     * Optimizado para respuestas rápidas con límite reducido
     * 
     * @param string $accessToken Token de acceso
     * @param string $query Término de búsqueda (mínimo 3 caracteres)
     * @return array Lista de usuarios encontrados
     */
    public function searchUsers(string $accessToken, string $query): array
    {
        try {
            // Sanear la query: solo permitir caracteres alfanuméricos, espacios, acentos y algunos símbolos seguros
            $safeQuery = trim($query);
            // Remover caracteres potencialmente peligrosos o que disparan alertas de seguridad
            $safeQuery = preg_replace('/[^a-zA-Z0-9áéíóúñÁÉÍÓÚÑüÜ\s@.\-_]/', '', $safeQuery);
            // Limitar longitud para evitar queries muy largas
            $safeQuery = substr($safeQuery, 0, 100);
            
            if (strlen($safeQuery) < 2) {
                Log::warning('Query de búsqueda muy corta o vacía después de sanear', [
                    'original' => $query,
                    'sanitized' => $safeQuery
                ]);
                return [];
            }
            
            Log::info('Iniciando búsqueda en Azure AD', [
                'query' => $safeQuery,
                'longitud' => strlen($safeQuery),
                'original_length' => strlen($query)
            ]);
            
            // Usar $search que es más flexible y rápido
            $response = $this->client->get(
                $this->baseUrl . '/users',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'ConsistencyLevel' => 'eventual'
                    ],
                    'query' => [
                        '$search' => '"displayName:' . $safeQuery . '" OR "givenName:' . $safeQuery . '" OR "surname:' . $safeQuery . '" OR "mail:' . $safeQuery . '" OR "userPrincipalName:' . $safeQuery . '"',
                        '$select' => 'id,displayName,mail,userPrincipalName,jobTitle,department,officeLocation,businessPhones,givenName,surname',
                        '$top' => 50, // Reducido de 100 a 50 para mejor performance
                        '$count' => 'true'
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('Búsqueda completada exitosamente', [
                'query' => $safeQuery,
                'results_count' => count($data['value'] ?? []),
                'total_count' => $data['@odata.count'] ?? 'N/A'
            ]);

            return $data['value'] ?? [];

        } catch (GuzzleException $e) {
            $errorBody = 'No disponible';
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
            }
            
            Log::error('Error buscando usuarios en Azure AD', [
                'query' => $query,
                'error' => $e->getMessage(),
                'status_code' => method_exists($e, 'getCode') ? $e->getCode() : 'N/A',
                'response_body' => $errorBody
            ]);
            
            throw new \Exception('Error al buscar usuarios en Microsoft 365: ' . $e->getMessage());
        }
    }

    /**
     * Envía un correo electrónico desde el usuario autenticado
     * 
     * @param string $accessToken Token de acceso del usuario
     * @param string $to Correo del destinatario
     * @param string $subject Asunto del correo
     * @param string $body Cuerpo del correo (HTML)
     * @param array $ccEmails Correos en copia (opcional)
     * @param array $attachments Adjuntos (opcional)
     * @return bool True si se envió exitosamente
     */
    public function sendMailAsUser(
        string $accessToken, 
        string $to, 
        string $subject, 
        string $body, 
        array $ccEmails = [],
        array $attachments = []
    ): bool {
        $message = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $body
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $to
                        ]
                    ]
                ]
            ],
            'saveToSentItems' => true // Guardar en enviados
        ];

        // Agregar CC si hay
        if (!empty($ccEmails)) {
            $message['message']['ccRecipients'] = array_map(function($email) {
                return ['emailAddress' => ['address' => $email]];
            }, $ccEmails);
        }

        // Agregar adjuntos si hay
        if (!empty($attachments)) {
            $message['message']['attachments'] = $attachments;
        }

        try {
            $response = $this->client->post(
                $this->baseUrl . '/me/sendMail',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ],
                    'json' => $message
                ]
            );

            $statusCode = $response->getStatusCode();

            if ($statusCode === 202) { // Accepted
                Log::info('Correo enviado exitosamente vía Microsoft Graph', [
                    'to' => $to,
                    'subject' => $subject
                ]);
                return true;
            }

            return false;

        } catch (GuzzleException $e) {
            Log::error('Error enviando correo vía Microsoft Graph', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'status' => $e->getCode()
            ]);
            return false;
        }
    }

    /**
     * Obtiene información detallada del usuario autenticado
     * 
     * @param string $accessToken Token de acceso
     * @return array|null Información del usuario
     */
    public function getMyProfile(string $accessToken): ?array
    {
        try {
            $response = $this->client->get(
                $this->baseUrl . '/me',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json'
                    ],
                    'query' => [
                        '$select' => 'id,displayName,mail,userPrincipalName,jobTitle,department,officeLocation,businessPhones,mobilePhone'
                    ]
                ]
            );

            return json_decode($response->getBody()->getContents(), true);

        } catch (GuzzleException $e) {
            Log::error('Error obteniendo perfil de usuario', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verifica si un correo pertenece a la organización
     * 
     * @param string $accessToken Token de acceso
     * @param string $email Correo a verificar
     * @return bool True si el usuario existe en Azure AD
     */
    public function isOrganizationEmail(string $accessToken, string $email): bool
    {
        $user = $this->getUserByEmail($accessToken, $email);
        return $user !== null;
    }

    /**
     * Obtiene la foto de perfil del usuario
     * 
     * @param string $accessToken Token de acceso
     * @return string|null Foto en base64 o null
     */
    public function getUserPhoto(string $accessToken): ?string
    {
        try {
            $response = $this->client->get(
                $this->baseUrl . '/me/photo/$value',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                    ]
                ]
            );

            $photoData = $response->getBody()->getContents();
            return base64_encode($photoData);

        } catch (GuzzleException $e) {
            // No todos los usuarios tienen foto
            return null;
        }
    }
}
