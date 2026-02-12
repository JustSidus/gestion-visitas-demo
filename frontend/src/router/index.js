import { createRouter, createWebHistory } from 'vue-router';
import Login from '../views/Login.vue';
import MicrosoftAuthService from '../services/MicrosoftAuthService';
import logger from '../utils/logger';
import { isTokenExpired, isValidTokenStructure } from '../utils/tokenValidator';

const routes = [
  { path: '/',
    name: 'login',
    component: Login,
    props: {titulo:'Login'}
  },
  { path: '/statistics',
    name: 'statistics',
    component: () => import('../views/StatsDashboard.vue'),
    meta: { 
      requiresAuth: true,
      title: 'Estadísticas y Reportes',
      requiresRole: ['Admin', 'Asist_adm'] // Solo Admin y Asist_adm ven estadísticas
    }
  },
  {
    path: '/visits',
    name: 'visits',
    component: () => import('../views/ActiveVisits.vue'),
    meta: { 
      requiresAuth: true,
      title: 'Visitas Activas',
      requiresRole: ['Admin', 'Asist_adm', 'Guardia'] // Asist_adm solo ve visitas NO misionales
    }
  },
  {
    path: '/mission-visits',
    name: 'mission-visits',
    component: () => import('../views/MissionActiveVisits.vue'),
    meta: { 
      requiresAuth: true,
      title: 'Visitas Activas Misionales',
      requiresRole: ['Admin', 'aux_ugc'] // Solo aux_ugc y Admin pueden ver casos misionales
    }
  },
  {
    path: '/visits/:visitId/visitor/:visitorId/alert',
    name: 'alert-register',
    component: () => import('../views/AlertRegister.vue'),
    meta: { 
      requiresAuth: true,
      title: 'Registrar Alerta',
      requiresRole: ['Admin', 'Asist_adm', 'aux_ugc']
    }
  },
  {
    path: '/crear-visitas',
    name: 'crear-visitas',
    component: () => import('../views/CreateVisit.vue'),
    meta: { 
      requiresAuth: true,
      title: 'Registrar Nueva Visita',
      requiresRole: ['Admin', 'Asist_adm'] // Solo Admin y Asist_adm pueden registrar
    }
  },
  {
    path: '/historial',
    name: 'historial',
    component: () => import('../views/VisitHistory.vue'),
    meta: { 
      requiresAuth: true,
      title: 'Historial de Visitas',
      requiresRole: ['Admin', 'Asist_adm'] // Solo Admin y Asist_adm ven historial
    }
  },
  {
    path: '/user-management',
    name: 'user-management',
    component: () => import('../views/UserManagement.vue'),
    meta: { 
      requiresAuth: true,
      title: 'Gestión de Usuarios',
      requiresRole: ['Admin'] // Solo Admin puede acceder
    }
  },
  { path: '/about', component: '' }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Guardia de navegación para proteger rutas
router.beforeEach(async (to, from, next) => {
  const token = localStorage.getItem('access_token');
  const userData = localStorage.getItem('user');
  let user = null;
  try {
    user = userData ? JSON.parse(userData) : null;
  } catch {
    user = null;
  }
  const userRole = user?.role;
  
  // ========================================================================
  // 1. VALIDACIÓN DE AUTENTICACIÓN MEJORADA
  // ========================================================================
  
  // Verificar si el token existe Y es válido
  let isAuthenticated = false;
  
  if (token) {
    // Validar estructura del token
    if (!isValidTokenStructure(token)) {
      logger.warn('Token con estructura inválida detectado, limpiando...');
      localStorage.removeItem('access_token');
      localStorage.removeItem('user');
      localStorage.removeItem('role');
      localStorage.removeItem('microsoft_access_token');
    } 
    // Validar si el token ha expirado
    else if (isTokenExpired(token)) {
      logger.warn('Token expirado detectado en router guard, limpiando...');
      localStorage.removeItem('access_token');
      localStorage.removeItem('user');
      localStorage.removeItem('role');
      localStorage.removeItem('microsoft_access_token');
    } 
    // Token válido y no expirado
    else {
      isAuthenticated = true;
    }
  }
  
  // ========================================================================
  // 2. PROTECCIÓN DE RUTAS QUE REQUIEREN AUTENTICACIÓN
  // ========================================================================
  
  // Si la ruta requiere autenticación y el usuario NO está autenticado o token expiró
  if (to.meta.requiresAuth && !isAuthenticated) {
    logger.warn('Acceso denegado: token inválido o expirado', {
      to: to.path,
      hasToken: !!token,
      isExpired: token ? isTokenExpired(token) : 'N/A'
    });
    
    // Limpiar todo antes de redirigir al login
    localStorage.removeItem('access_token');
    localStorage.removeItem('user');
    localStorage.removeItem('role');
    localStorage.removeItem('microsoft_access_token');
    
    next('/'); // Redirigir al login
    return;
  }
  
  // ========================================================================
  // 3. EVITAR LOGIN SI YA ESTÁ AUTENTICADO
  // ========================================================================
  
  // Si el usuario intenta acceder al login estando ya autenticado
  if (to.path === '/' && isAuthenticated) {
    logger.log('Usuario ya autenticado, redirigiendo a su vista principal');
    
    // Redirigir según el rol del usuario
    if (userRole === 'aux_ugc') {
      next('/mission-visits');
    } else {
      next('/visits');
    }
    return;
  }
  
  // ========================================================================
  // 4. VERIFICACIÓN DE ROLES
  // ========================================================================
  
  // Verificar si el usuario tiene el rol requerido
  if (to.meta.requiresRole && userRole && isAuthenticated) {
    const allowedRoles = to.meta.requiresRole;
    
    if (!allowedRoles.includes(userRole)) {
      logger.warn('Acceso denegado por rol', {
        userRole,
        allowedRoles,
        path: to.path
      });
      
      // El usuario no tiene el rol requerido, redirigir según su rol
      if (userRole === 'aux_ugc') {
        next('/mission-visits');
      } else {
        next('/visits');
      }
      return;
    }
  }
  
  // ========================================================================
  // 5. PERMITIR NAVEGACIÓN
  // ========================================================================
  
  // En cualquier otro caso, permitir la navegación
  next();
});

export default router
