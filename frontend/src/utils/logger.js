/**
 * Logger seguro para la aplicación
 * Solo muestra logs en desarrollo, nunca en producción
 * No expone información sensible
 */

class SecureLogger {
  constructor() {
    this.isDevelopment = import.meta.env.DEV;
    this.isProduction = import.meta.env.PROD;
  }

  /**
   * Log seguro - solo en desarrollo
   */
  log(message, data = null) {
    if (!this.isDevelopment) return;

    if (data && this.containsSensitiveData(data)) {
      console.log(` ${message}`, '[DATA HIDDEN]');
    } else {
      console.log(` ${message}`, data);
    }
  }

  /**
   * Log de éxito - solo en desarrollo
   */
  success(message, data = null) {
    if (!this.isDevelopment) return;

    if (data && this.containsSensitiveData(data)) {
      console.log(` ${message}`, '[DATA HIDDEN]');
    } else {
      console.log(` ${message}`, data);
    }
  }

  /**
   * Log de warning - solo en desarrollo
   */
  warn(message, data = null) {
    if (!this.isDevelopment) return;

    if (data && this.containsSensitiveData(data)) {
      console.warn(`️ ${message}`, '[DATA HIDDEN]');
    } else {
      console.warn(`️ ${message}`, data);
    }
  }

  /**
   * Log de error - solo en desarrollo
   */
  error(message, error = null) {
    if (!this.isDevelopment) return;

    if (error && this.containsSensitiveData(error)) {
      console.error(` ${message}`, '[ERROR DATA HIDDEN]');
    } else {
      console.error(` ${message}`, error);
    }
  }

  /**
   * Log crítico - siempre se muestra (pero sin datos sensibles)
   */
  critical(message) {
    console.error(` ${message}`);
  }

  /**
   * Log de diagnóstico - SOLO para producción (temporal)
   * Muestra información no sensible para diagnosticar problemas
   */
  diagnostic(message, data = null) {
    // Filtrar datos sensibles antes de loguear
    let safeData = data;
    if (data && typeof data === 'object') {
      safeData = {};
      for (const key in data) {
        if (this.isSensitiveKey(key)) {
          safeData[key] = '[HIDDEN]';
        } else {
          safeData[key] = data[key];
        }
      }
    }
    console.log(` [DIAGNOSTIC] ${message}`, safeData);
  }

  /**
   * Verifica si una key es sensible
   */
  isSensitiveKey(key) {
    const sensitive = ['token', 'password', 'secret', 'key', 'auth', 'bearer', 'jwt'];
    return sensitive.some(s => key.toLowerCase().includes(s));
  }

  /**
   * Verifica si los datos contienen información sensible
   */
  containsSensitiveData(data) {
    if (!data) return false;

    const sensitiveKeys = [
      'token', 'access_token', 'password', 'secret', 'key',
      'authorization', 'bearer', 'jwt', 'auth',
      'microsoft_access_token', 'refresh_token'
    ];

    const dataString = JSON.stringify(data).toLowerCase();

    return sensitiveKeys.some(key =>
      dataString.includes(key) &&
      (dataString.includes('eyj') || // JWT tokens empiezan con eyJ
       dataString.includes('bearer') ||
       dataString.length > 50) // Tokens largos
    );
  }

  /**
   * Log de autenticación (muy limitado)
   */
  auth(message, userInfo = null) {
    if (!this.isDevelopment) return;

    if (userInfo) {
      // Solo mostrar información no sensible
      const safeInfo = {
        email: userInfo.email || userInfo.mail || userInfo.userPrincipalName,
        name: userInfo.displayName || userInfo.name,
        id: userInfo.id ? '[ID PRESENT]' : undefined
      };
      console.log(` ${message}`, safeInfo);
    } else {
      console.log(` ${message}`);
    }
  }

  /**
   * Log de API (sin tokens)
   */
  api(message, data = null) {
    if (!this.isDevelopment) return;

    if (data && this.containsSensitiveData(data)) {
      console.log(` ${message}`, '[API DATA HIDDEN]');
    } else {
      console.log(` ${message}`, data);
    }
  }
}

// Instancia global del logger
const logger = new SecureLogger();

export default logger;
