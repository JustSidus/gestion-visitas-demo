/**
 * Utilidades para formateo de datos de visitas
 * Centraliza funciones duplicadas en ActiveVisits, MissionActiveVisits, VisitHistory, etc.
 */

/**
 * Obtiene la etiqueta del tipo de documento del visitante
 * @param {Object} visit - Objeto de visita
 * @returns {string} Tipo de documento formateado
 */
export const getDocumentType = (visit) => {
  // Validación defensiva: verificar que el visitante existe
  if (!visit?.visitors || !Array.isArray(visit.visitors) || visit.visitors.length === 0) {
    return 'No capturado';
  }

  const visitor = visit.visitors[0];
  
  // Intentar obtener document_type_label primero (viene del backend)
  if (visitor?.document_type_label && visitor.document_type_label.trim() !== '') {
    return visitor.document_type_label;
  }
  
  // Fallback a mapeo manual si la etiqueta no viene en la respuesta
  if (visitor?.document_type) {
    const tipoDocumento = Number(visitor.document_type);
    return tipoDocumento === 1 ? 'Cédula' : 
           tipoDocumento === 2 ? 'Pasaporte' :
           tipoDocumento === 3 ? 'Sin Identificación' : 'No capturado';
  }
  
  return 'No capturado';
};

/**
 * Obtiene el número de documento de identidad del visitante
 * @param {Object} visit - Objeto de visita
 * @returns {string} Número de documento
 */
export const getIdentityNumber = (visit) => {
  // Validación defensiva: verificar que el visitante existe
  if (!visit?.visitors || !Array.isArray(visit.visitors) || visit.visitors.length === 0) {
    return 'No disponible';
  }
  
  const visitor = visit.visitors[0];
  
  // Ocultar el número si la etiqueta indica 'Sin Identificación'
  const docTypeLabel = getDocumentType(visit).toString().toLowerCase();
  if (docTypeLabel.includes('sin identific')) {
    return '';
  }
  
  // Retornar documento de identidad o fallback
  if (visitor?.identity_document && String(visitor.identity_document).trim() !== '') {
    return String(visitor.identity_document).trim();
  }
  
  return 'No disponible';
};

/**
 * Obtiene el documento formateado combinando tipo y número
 * @param {Object} visit - Objeto de visita
 * @returns {string} Documento formateado "Tipo: Número"
 */
export const getFormattedDocument = (visit) => {
  const type = getDocumentType(visit);
  const number = getIdentityNumber(visit);
  
  if (type === 'Sin Identificación' || number === 'N/A') {
    return 'Sin Identificación';
  }
  
  return `${type}: ${number}`;
};

/**
 * Formatea una fecha/hora ISO a formato legible (HH:mm)
 * @param {string|Object} dateString - Fecha en formato ISO o objeto con propiedad 'time'
 * @returns {string} Hora formateada
 */
export const formatTime = (dateString) => {
  if (!dateString) return '—';
  
  // Si es un objeto con propiedad 'time' (viene del backend formateado)
  if (typeof dateString === 'object' && dateString !== null && dateString.time) {
    // Eliminar los segundos del formato HH:MM:SS si existen
    const time = dateString.time;
    return time.length > 5 ? time.substring(0, 5) : time;
  }
  
  // Si es un string directo, parsearlo como fecha
  if (typeof dateString === 'string') {
    try {
      const date = new Date(dateString);
      if (isNaN(date.getTime())) return '—';
      
      return date.toLocaleTimeString('es-DO', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
      });
    } catch {
      return '—';
    }
  }
  
  return '—';
};

/**
 * Formatea una fecha ISO a formato legible (DD/MM/YYYY)
 * @param {string} dateString - Fecha en formato ISO
 * @returns {string} Fecha formateada
 */
export const formatDate = (dateString) => {
  if (!dateString) return '—';
  
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '—';
    
    return date.toLocaleDateString('es-DO', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  } catch {
    return '—';
  }
};

/**
 * Formatea fecha y hora completa
 * @param {string} dateString - Fecha en formato ISO
 * @returns {string} Fecha y hora formateada
 */
export const formatDateTime = (dateString) => {
  if (!dateString) return '—';
  
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '—';
    
    return `${formatDate(dateString)} ${formatTime(dateString)}`;
  } catch {
    return '—';
  }
};

/**
 * Calcula la duración entre dos fechas
 * @param {string} startDate - Fecha de inicio
 * @param {string} endDate - Fecha de fin (opcional, usa now si no se proporciona)
 * @returns {string} Duración formateada
 */
export const calculateDuration = (startDate, endDate = null) => {
  if (!startDate) return '—';
  
  try {
    const start = new Date(startDate);
    const end = endDate ? new Date(endDate) : new Date();
    
    if (isNaN(start.getTime())) return '—';
    if (endDate && isNaN(end.getTime())) return '—';
    
    const diffMs = end - start;
    const diffMins = Math.floor(diffMs / 60000);
    const hours = Math.floor(diffMins / 60);
    const mins = diffMins % 60;
    
    if (hours > 0) {
      return `${hours}h ${mins}m`;
    }
    return `${mins}m`;
  } catch {
    return '—';
  }
};

/**
 * Obtiene el nombre completo del visitante
 * @param {Object} visit - Objeto de visita
 * @returns {string} Nombre completo
 */
export const getVisitorFullName = (visit) => {
  if (!visit?.visitors || !Array.isArray(visit.visitors) || visit.visitors.length === 0) {
    return 'Visitante no registrado';
  }
  
  const visitor = visit.visitors[0];
  const name = visitor?.name || '';
  const lastName = visitor?.lastName || visitor?.last_name || '';
  
  return `${name} ${lastName}`.trim() || 'Visitante no registrado';
};

/**
 * Constantes de roles del sistema
 */
export const ROLES = {
  ADMIN: 'Admin',
  ASIST_ADM: 'Asist_adm',
  GUARDIA: 'Guardia',
  AUX_UGC: 'aux_ugc'
};

/**
 * Verifica si el usuario tiene un rol específico
 * @param {string} userRole - Rol del usuario
 * @param {string|string[]} allowedRoles - Rol o roles permitidos
 * @returns {boolean}
 */
export const hasRole = (userRole, allowedRoles) => {
  if (!userRole) return false;
  
  if (Array.isArray(allowedRoles)) {
    return allowedRoles.includes(userRole);
  }
  
  return userRole === allowedRoles;
};

/**
 * Obtiene el usuario del localStorage de forma segura
 * @returns {Object|null} Datos del usuario o null
 */
export const getSafeUser = () => {
  try {
    const userData = localStorage.getItem('user');
    return userData ? JSON.parse(userData) : null;
  } catch {
    return null;
  }
};

/**
 * Obtiene el rol del usuario de forma segura
 * @returns {string|null} Rol del usuario o null
 */
export const getSafeUserRole = () => {
  const user = getSafeUser();
  return user?.role || null;
};
