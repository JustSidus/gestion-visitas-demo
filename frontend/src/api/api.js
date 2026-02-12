
import axios from 'axios';
import AuthService from '../services/AuthService';
import logger from '../utils/logger';

// ============================================================================
// CONTROL DE RENOVACIÓN DE TOKEN (evitar múltiples intentos simultáneos)
// ============================================================================
let isRefreshing = false;
let failedQueue = [];

const processQueue = (error, token = null) => {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });
  
  failedQueue = [];
};

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api',
    headers: { 'Content-Type': 'application/json' },
    timeout: 30000 // 30 segundos de timeout
});

// Agregar token de autenticación si existe en localStorage
api.interceptors.request.use(config => {
    const token = localStorage.getItem('access_token');
    if (token) {
        config.headers['Authorization'] = `Bearer ${token}`;
        logger.api('Token enviado en request', '[TOKEN PRESENT]');
    } else {
        logger.warn('No hay token en localStorage');
    }
    return config;
}, error => {
    return Promise.reject(error);
});

// Interceptor de respuesta para manejo global de errores y renovación de token
api.interceptors.response.use(
    response => response,
    async error => {
        const originalRequest = error.config;

        if (error.response) {
            // Errores del servidor (4xx, 5xx)
            switch (error.response.status) {
                case 401:
                    // ================================================================
                    // Token expirado - Sistema de cola para evitar múltiples intentos
                    // ================================================================
                    
                    // Si ya se está renovando el token, agregar esta petición a la cola
                    if (isRefreshing) {
                        logger.log('Token refresh en progreso, agregando petición a la cola');
                        
                        return new Promise((resolve, reject) => {
                            failedQueue.push({ resolve, reject });
                        })
                        .then(token => {
                            originalRequest.headers['Authorization'] = `Bearer ${token}`;
                            return api(originalRequest);
                        })
                        .catch(err => {
                            return Promise.reject(err);
                        });
                    }
                    
                    // Si no se ha intentado renovar y no hay otro proceso renovando
                    if (!originalRequest._retry) {
                        originalRequest._retry = true;
                        isRefreshing = true;

                        logger.warn('Token expirado, intentando renovar con Microsoft');

                        try {
                            // Intentar obtener nuevo token de Microsoft
                            const MicrosoftAuthService = (await import('../services/MicrosoftAuthService')).default;
                            const newMicrosoftToken = await MicrosoftAuthService.getAccessToken();

                            if (newMicrosoftToken) {
                                // Guardar nuevo token de Microsoft
                                localStorage.setItem('microsoft_access_token', newMicrosoftToken);

                                // Enviar al backend para obtener nuevo JWT
                                const response = await axios.post(
                                    `${import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'}/auth/microsoft-refresh`,
                                    { access_token: newMicrosoftToken }
                                );

                                const newToken = response.data.access_token;
                                
                                // Guardar nuevo JWT
                                localStorage.setItem('access_token', newToken);

                                logger.success('Token renovado exitosamente');
                                
                                // Procesar todas las peticiones en cola con el nuevo token
                                processQueue(null, newToken);
                                isRefreshing = false;

                                // Reintentar la petición original con el nuevo token
                                originalRequest.headers['Authorization'] = `Bearer ${newToken}`;
                                return api(originalRequest);
                            } else {
                                throw new Error('No se pudo obtener token de Microsoft');
                            }
                        } catch (refreshError) {
                            logger.error('No se pudo renovar el token', refreshError);
                            
                            // Fallar todas las peticiones en cola
                            processQueue(refreshError, null);
                            isRefreshing = false;
                            
                            // Limpiar y redirigir al login
                            logger.warn('Token inválido, redirigiendo al login');
                            try {
                                await AuthService.clearClientStorage();
                            } catch (e) {
                                logger.warn('clearClientStorage failed during interceptor handling', e);
                                // Fallback to remove common keys
                                localStorage.removeItem('access_token');
                                localStorage.removeItem('user');
                                localStorage.removeItem('microsoft_access_token');
                            }
                            
                            // Evitar múltiples redirects
                            if (window.location.pathname !== '/') {
                                window.location.href = '/';
                            }
                            
                            return Promise.reject(refreshError);
                        }
                    }
                    
                    // Si ya se intentó renovar y falló, limpiar y redirigir
                    logger.warn('Token inválido después de reintento, redirigiendo al login');
                    try {
                        await AuthService.clearClientStorage();
                    } catch (e) {
                        logger.warn('clearClientStorage failed', e);
                        localStorage.removeItem('access_token');
                        localStorage.removeItem('user');
                        localStorage.removeItem('microsoft_access_token');
                    }
                    
                    // Evitar múltiples redirects
                    if (window.location.pathname !== '/') {
                        window.location.href = '/';
                    }
                    break;
                    
                case 403:
                    logger.error('No tienes permisos para realizar esta acción');
                    break;
                case 404:
                    logger.error('Recurso no encontrado');
                    break;
                case 422:
                    // Errores de validación
                    logger.error('Error de validación', error.response.data);
                    break;
                case 500:
                    logger.error('Error del servidor. Por favor, intenta más tarde');
                    break;
                default:
                    logger.error('Error en la solicitud', error.response.data);
            }
        } else if (error.request) {
            // Error de red (sin respuesta del servidor)
            logger.error('Error de conexión. Verifica tu conexión a internet');
        } else {
            // Error al configurar la solicitud
            logger.error('Error en configuración de solicitud', error.message);
        }
        return Promise.reject(error);
    }
);

// ===========================================================================
// ENDPOINTS DE MICROSOFT AUTH
// ===========================================================================

export const microsoftAuthAPI = {
    /**
     * Login con Microsoft 365
     */
    login: (accessToken, microsoftUser) => {
        return api.post('/auth/microsoft-login', {
            access_token: accessToken,
            microsoft_user: microsoftUser
        });
    },

    /**
     * Logout
     */
    logout: () => {
        return api.post('/auth/logout');
    },

    /**
     * Renovar token JWT
     */
    refresh: () => {
        return api.post('/auth/refresh');
    },

    /**
     * Obtener datos del usuario autenticado
     */
    me: () => {
        return api.get('/auth/me');
    }
};

// ===========================================================================
// ENDPOINTS DE GESTIÓN DE USUARIOS (ADMIN)
// ===========================================================================

export const userManagementAPI = {
    /**
     * Listar todos los usuarios de la app
     */
    getUsers: () => {
        return api.get('/admin/users');
    },

    /**
     * Obtener roles disponibles
     */
    getRoles: () => {
        return api.get('/admin/users/roles');
    },

    /**
     * Buscar usuarios en Microsoft 365
     */
    searchMicrosoftUsers: (query) => {
        return api.get('/admin/users/search-microsoft', {
            params: { query }
        });
    },

    /**
     * Agregar usuario a la app
     */
    addUser: (userData) => {
        return api.post('/admin/users', userData);
    },

    /**
     * Actualizar usuario
     */
    updateUser: (userId, userData) => {
        return api.put(`/admin/users/${userId}`, userData);
    },

    /**
     * Activar/desactivar usuario
     */
    toggleUserActive: (userId) => {
        return api.patch(`/admin/users/${userId}/toggle-active`);
    },

    /**
     * Eliminar usuario
     */
    deleteUser: (userId) => {
        return api.delete(`/admin/users/${userId}`);
    }
};

export default api;
