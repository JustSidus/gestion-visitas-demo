/**
 * Servicio de autenticación con Microsoft 365
 * 
 * Maneja el flujo de SSO (Single Sign-On) con Azure AD
 * utilizando MSAL (Microsoft Authentication Library)
 */

import * as msal from '@azure/msal-browser';
import logger from '../utils/logger';

// Logging de configuración para diagnóstico
logger.diagnostic(' MSAL Config Info', {
  clientId: import.meta.env.VITE_MICROSOFT_CLIENT_ID,
  tenantId: import.meta.env.VITE_MICROSOFT_TENANT_ID,
  redirectUri: import.meta.env.VITE_MICROSOFT_REDIRECT_URI,
  windowOrigin: window.location.origin,
  finalRedirectUri: import.meta.env.VITE_MICROSOFT_REDIRECT_URI || window.location.origin,
  environment: import.meta.env.MODE,
});

// Configuración de MSAL
const msalConfig = {
  auth: {
    clientId: import.meta.env.VITE_MICROSOFT_CLIENT_ID,
    authority: `https://login.microsoftonline.com/${import.meta.env.VITE_MICROSOFT_TENANT_ID}`,
    redirectUri: import.meta.env.VITE_MICROSOFT_REDIRECT_URI || window.location.origin,
    postLogoutRedirectUri: import.meta.env.VITE_MICROSOFT_REDIRECT_URI || window.location.origin,
  },
  cache: {
    cacheLocation: 'sessionStorage',
    storeAuthStateInCookie: true,
  },
  system: {
    allowNativeBroker: false,
    loggerOptions: {
      loggerCallback: (level, message, containsPii) => {
        if (containsPii) return;
        switch (level) {
          case msal.LogLevel.Error:
            console.error('[MSAL Error]', message);
            return;
          case msal.LogLevel.Warning:
            console.warn('[MSAL Warning]', message);
            return;
          // Mostrar TODOS los logs MSAL en producción para diagnóstico
          case msal.LogLevel.Info:
            console.log('[MSAL Info]', message);
            return;
          case msal.LogLevel.Verbose:
            console.log('[MSAL Verbose]', message);
            return;
          default:
            return;
        }
      },
      piiLoggingEnabled: false,
    },
  },
};

//  LOG DIAGNÓSTICO: Configuración MSAL (solo valores no sensibles)
logger.diagnostic(' MSAL Configuration', {
  clientId: import.meta.env.VITE_MICROSOFT_CLIENT_ID,
  tenantId: import.meta.env.VITE_MICROSOFT_TENANT_ID,
  redirectUri_ENV: import.meta.env.VITE_MICROSOFT_REDIRECT_URI,
  redirectUri_ORIGIN: window.location.origin,
  redirectUri_FINAL: import.meta.env.VITE_MICROSOFT_REDIRECT_URI || window.location.origin,
  environment: import.meta.env.MODE,
  currentUrl: window.location.href,
});

// Scopes necesarios para la aplicación
const loginRequest = {
  scopes: [
    'User.Read',           // Leer perfil del usuario
    'User.ReadBasic.All',  // Buscar otros usuarios
    'Mail.Send',           // Enviar correos
  ],
};

// Crear instancia de MSAL
let msalInstance = null;
// Guard to avoid concurrent interactive calls (login/acquireToken popups)
let interactionInProgress = false;

// Utility: clear MSAL-related entries from storage and reset in-memory instance
const clearMsalState = () => {
  try {
    if (typeof window === 'undefined') return;

    ['localStorage', 'sessionStorage'].forEach((stor) => {
      try {
        const storage = stor === 'localStorage' ? window.localStorage : window.sessionStorage;
        const keys = Object.keys(storage || {});
        keys.forEach((k) => {
          if (!k) return;
          // remove keys related to msal or interaction state
          if (/msal|interaction|auth|account/i.test(k)) {
            storage.removeItem(k);
          }
        });
      } catch (e) {
        // ignore per-storage errors
      }
    });

    // Also try to clean any global msal info added by the library (HMR/dev)
    try {
      if (window.msal && Array.isArray(window.msal.clientIds)) {
        window.msal.clientIds = window.msal.clientIds.filter(id => id !== msalConfig.auth.clientId);
      }
      // remove global msal only if empty
      if (window.msal && (!window.msal.clientIds || window.msal.clientIds.length === 0)) {
        try { delete window.msal; } catch(e) { window.msal = undefined; }
      }
    } catch(e) {
      // ignore
    }

    // Reset in-memory instance and interaction flag
    msalInstance = null;
    interactionInProgress = false;

    logger.success('MSAL state cleared from storage and in-memory instance reset');
  } catch (err) {
    logger.warn('Unable to fully clear MSAL state', err);
  }
};

/**
 * Inicializa la instancia de MSAL
 */
const initializeMsal = async () => {
  if (!msalInstance) {
    logger.diagnostic(' Initializing MSAL Instance', {
      clientId: msalConfig.auth.clientId,
      redirectUri: msalConfig.auth.redirectUri,
      authority: msalConfig.auth.authority,
    });
    
    msalInstance = new msal.PublicClientApplication(msalConfig);
    await msalInstance.initialize();
    
    // Procesar cualquier redirect pendiente
    try {
      logger.diagnostic(' Handling Redirect Promise...');
      const response = await msalInstance.handleRedirectPromise();
      if (response) {
        logger.success('Redirect de Microsoft procesado', { username: response.account?.username });
        logger.diagnostic(' Redirect Success', {
          accountId: response.account?.homeAccountId?.substring(0, 10) + '...',
          username: response.account?.username,
          scopes: response.scopes,
        });
      } else {
        logger.diagnostic('ℹ️ No pending redirect');
      }
    } catch (error) {
      logger.error('Error procesando redirect', error);
      logger.diagnostic(' Redirect Error', {
        errorCode: error.errorCode,
        errorMessage: error.errorMessage?.substring(0, 100),
      });
    }
  }
  return msalInstance;
};

/**
 * Servicio de autenticación con Microsoft
 */
const MicrosoftAuthService = {
  /**
   * Inicia sesión con Microsoft 365 (usa redirect para mejor compatibilidad)
   * 
   * @returns {Promise<Object>} { accessToken, user } - Solo retorna si ya hay sesión activa
   */
  async login() {
    try {
      if (interactionInProgress) {
        throw new Error('Ya hay un proceso de inicio de sesión en curso');
      }
      
      const instance = await initializeMsal();
      const accounts = instance.getAllAccounts();
      
      //  LOG DIAGNÓSTICO: Estado antes del login
      logger.diagnostic(' Login Start', {
        accountsFound: accounts.length,
        currentUrl: window.location.href,
        origin: window.location.origin,
        configuredRedirectUri: msalConfig.auth.redirectUri,
      });
      
      // Si ya hay una cuenta activa, obtener token y retornar
      if (accounts.length > 0) {
        logger.log('Cuenta ya autenticada, obteniendo token...');
        logger.diagnostic(' Account Found', { 
          username: accounts[0].username,
          homeAccountId: accounts[0].homeAccountId?.substring(0, 10) + '...',
        });
        
        const accessToken = await this.getAccessToken();
        const user = await this.getUserInfo();
        
        if (accessToken && user) {
          return { accessToken, user };
        }
      }
      
      // No hay cuenta activa, iniciar redirect
      interactionInProgress = true;
      logger.log('Iniciando login con redirect...');
      
      //  LOG DIAGNÓSTICO: Datos del redirect
      logger.diagnostic(' Redirect to Microsoft', {
        scopes: loginRequest.scopes,
        redirectUri: msalConfig.auth.redirectUri,
        authority: msalConfig.auth.authority,
        clientId: msalConfig.auth.clientId,
      });
      
      await instance.loginRedirect({ 
        ...loginRequest, 
        prompt: 'select_account',
        redirectStartPage: window.location.href 
      });
      
      // El código no llegará aquí porque loginRedirect hace un redirect completo
      // La respuesta se procesará en handleRedirectPromise() cuando vuelva

    } catch (error) {
      interactionInProgress = false;
      logger.error('Error en login con Microsoft', error);
      
      //  LOG DIAGNÓSTICO: Error en login
      logger.diagnostic(' Login Error', {
        errorCode: error.errorCode,
        errorMessage: error.errorMessage?.substring(0, 100),
        name: error.name,
      });

      // Errores comunes
      if (error.errorCode === 'user_cancelled') {
        throw new Error('Has cancelado el inicio de sesión');
      } else if (error.errorCode === 'interaction_in_progress') {
        throw new Error('Ya hay un proceso de inicio de sesión en curso');
      }

      throw new Error(error.errorMessage || error.message || 'Error al iniciar sesión con Microsoft');
    }
  },

  /**
   * Obtiene el access token actual o lo renueva
   * 
   * @returns {Promise<string>} Access token
   */
  async getAccessToken() {
    try {
      const instance = await initializeMsal();
      const accounts = instance.getAllAccounts();

      if (accounts.length === 0) {
        logger.warn('No hay cuentas activas');
        return null;
      }

      const request = {
        ...loginRequest,
        account: accounts[0],
      };

      try {
        // Intentar obtener token silenciosamente
        const response = await instance.acquireTokenSilent(request);
        logger.log('Token obtenido silenciosamente');
        return response.accessToken;
      } catch (error) {
        logger.error('Error obteniendo token silenciosamente', error);
        // Si falla el silent, el token probablemente no está disponible
        return null;
      }
    } catch (error) {
      logger.error('Error al obtener access token', error);
      return null;
    }
  },

  /**
   * Obtiene la cuenta activa actual
   * 
   * @returns {Object|null} Cuenta de Microsoft
   */
  async getCurrentAccount() {
    try {
      const instance = await initializeMsal();
      const accounts = instance.getAllAccounts();
      return accounts.length > 0 ? accounts[0] : null;
    } catch (error) {
      logger.error('Error al obtener cuenta actual', error);
      return null;
    }
  },

  /**
   * Cierra sesión de la aplicación (solo limpia tokens locales, mantiene sesión de Microsoft)
   */
  async logoutApp() {
    try {
      // Solo limpiar tokens de la app, NO cerrar sesión de Microsoft
      logger.success('Sesión de la app cerrada (sesión de Microsoft mantenida)');
    } catch (error) {
      logger.error('Error al cerrar sesión de la app', error);
      throw error;
    }
  },

  /**
   * Cierra sesión completa de Microsoft (elimina cookies y tokens)
   */
  async logoutMicrosoft() {
    try {
      // Simple, silent logout: clear local MSAL state and in-memory instance.
      // Do NOT open popups or perform redirects. The app's UI should handle
      // navigation after this call (e.g., redirect to '/').
      clearMsalState();
      logger.success('MSAL local state cleared (silent logout)');
    } catch (error) {
      logger.error('Error al cerrar sesión de Microsoft', error);
      // Ensure local cleanup even if something unexpected happens
      clearMsalState();
    }
  },

  /**
   * Verifica si hay una sesión activa
   * 
   * @returns {Promise<boolean>}
   */
  async isAuthenticated() {
    try {
      const instance = await initializeMsal();
      const accounts = instance.getAllAccounts();
      return accounts.length > 0;
    } catch (error) {
      logger.error('Error al verificar autenticación', error);
      return false;
    }
  },

  /**
   * Obtiene información del usuario autenticado
   * 
   * @returns {Promise<Object|null>}
   */
  async getUserInfo() {
    try {
      const account = await this.getCurrentAccount();
      
      if (!account) {
        return null;
      }

      return {
        id: account.localAccountId,
        mail: account.username,
        displayName: account.name,
        userPrincipalName: account.username,
      };
    } catch (error) {
      logger.error('Error al obtener info del usuario', error);
      return null;
    }
  },

  /**
   * Limpiar estado de MSAL (storage e instancia en memoria).
   * Útil para recuperar de estados interrumpidos (interaction_in_progress) durante desarrollo
   * o cuando quede una instancia/viejo estado en storage.
   */
  clearMsalState() {
    clearMsalState();
  },
};

export default MicrosoftAuthService;

