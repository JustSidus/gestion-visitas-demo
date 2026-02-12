/**
 * Utilidades para validación de tokens JWT
 */

/**
 * Decodifica un JWT sin verificar la firma (solo para leer claims)
 * @param {string} token - Token JWT
 * @returns {object|null} Payload decodificado o null si es inválido
 */
export function decodeJWT(token) {
  try {
    if (!token || typeof token !== 'string') return null;
    
    const parts = token.split('.');
    if (parts.length !== 3) return null;
    
    // Decodificar la parte del payload (segunda parte)
    const payload = parts[1];
    const decoded = JSON.parse(atob(payload.replace(/-/g, '+').replace(/_/g, '/')));
    
    return decoded;
  } catch (error) {
    console.error('Error decodificando JWT:', error);
    return null;
  }
}

/**
 * Verifica si un token JWT ha expirado
 * @param {string} token - Token JWT
 * @returns {boolean} true si el token está expirado o es inválido
 */
export function isTokenExpired(token) {
  const decoded = decodeJWT(token);
  
  if (!decoded || !decoded.exp) {
    return true; // Si no se puede decodificar o no tiene 'exp', considerar expirado
  }
  
  // exp está en segundos, Date.now() en milisegundos
  const currentTime = Math.floor(Date.now() / 1000);
  const expirationTime = decoded.exp;
  
  // Agregar un margen de 30 segundos para evitar race conditions
  return currentTime >= (expirationTime - 30);
}

/**
 * Obtiene el tiempo restante hasta la expiración del token en segundos
 * @param {string} token - Token JWT
 * @returns {number} Segundos hasta expiración (negativo si ya expiró)
 */
export function getTokenTimeRemaining(token) {
  const decoded = decodeJWT(token);
  
  if (!decoded || !decoded.exp) {
    return -1;
  }
  
  const currentTime = Math.floor(Date.now() / 1000);
  return decoded.exp - currentTime;
}

/**
 * Verifica si un token está próximo a expirar (menos de 5 minutos)
 * @param {string} token - Token JWT
 * @returns {boolean}
 */
export function isTokenExpiringSoon(token) {
  const timeRemaining = getTokenTimeRemaining(token);
  return timeRemaining > 0 && timeRemaining < 300; // 5 minutos
}

/**
 * Valida que el token tenga una estructura y claims válidos
 * @param {string} token - Token JWT
 * @returns {boolean}
 */
export function isValidTokenStructure(token) {
  const decoded = decodeJWT(token);
  
  if (!decoded) return false;
  
  // Verificar que tenga los claims requeridos
  const requiredClaims = ['iss', 'iat', 'exp', 'sub'];
  return requiredClaims.every(claim => decoded.hasOwnProperty(claim));
}
