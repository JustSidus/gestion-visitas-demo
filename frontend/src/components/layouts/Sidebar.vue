<template>
  <aside
    :class="sidebarClasses"
    class="sidebar-transition fixed left-0 top-0 h-screen bg-institutional-gradient z-40 flex flex-col border-r border-demo-blue-800/30 shadow-institutional"
  >
    <!-- Logo Section -->
    <div class="h-24 flex items-center justify-center px-3 border-b border-demo-blue-800/30 overflow-hidden">
      <router-link to="/visits" class="w-full flex items-center justify-center transition-transform duration-200">
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 scale-90"
          leave-active-class="transition-all duration-200 ease-in"
          leave-to-class="opacity-0 scale-90"
          mode="out-in"
        >
          <!-- Logo Isotipo cuando está colapsado -->
          <div v-if="collapsed" class="flex items-center justify-center w-full">
            <LogoInstitucionDemo 
              variant="isotipo" 
              height="h-11" 
              width="w-11"
              image-class="drop-shadow-lg"
            />
          </div>
          
          <!-- Logo Horizontal cuando está expandido -->
          <div v-else class="flex flex-col items-center justify-center w-full px-1">
            <div class="max-w-full">
              <LogoInstitucionDemo 
                variant="horizontal" 
                height="h-12" 
                width="w-auto"
                image-class="drop-shadow-lg max-w-full object-contain transition-transform hover:scale-105 duration-200"
              />
            </div>
            <span class="text-[11px] text-demo-blue-100 mt-0.5 font-medium leading-none">Sistema de Visitas</span>
          </div>
        </Transition>
      </router-link>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto scrollbar-thin py-4 px-3">
      <div class="space-y-1">
        <!-- Section Label (solo cuando está expandido) -->
        <Transition
          enter-active-class="transition-all duration-200"
          enter-from-class="opacity-0 -translate-y-1"
          leave-active-class="transition-all duration-150"
          leave-to-class="opacity-0 -translate-y-1"
        >
          <div v-if="!collapsed" class="px-3 py-2 mb-2">
            <span class="text-xs font-semibold text-demo-blue-100 uppercase tracking-wider">
              Menú Principal
            </span>
          </div>
        </Transition>
        
        <!-- Menu Items -->
        <div v-for="item in visibleMenuItems" :key="item.to">
          <router-link
            :to="item.to"
            v-slot="{ isActive }"
            custom
          >
            <a
              :href="item.to"
              @click.prevent="navigateAndCollapse(item.to)"
              :class="[
                'flex items-center gap-3 px-3 py-3 rounded-xl transition-all duration-200 group relative',
                (isActive || isAlertPageForMissionVisits(item.to))
                  ? 'bg-white/20 text-white shadow-lg backdrop-blur-sm'
                  : 'text-demo-blue-100 hover:bg-white/10 hover:text-white'
              ]"
              :title="collapsed ? item.label : ''"
            >
              <!-- Active indicator (barra lateral) -->
              <div 
                v-if="isActive || isAlertPageForMissionVisits(item.to)" 
                class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-white rounded-r-full -ml-3 shadow-lg"
              ></div>
              
              <!-- Icon with glow effect when active -->
              <div class="relative flex-shrink-0">
                <Icon
                  :icon="item.icon"
                  :class="[
                    'w-6 h-6 transition-all duration-200',
                    (isActive || isAlertPageForMissionVisits(item.to)) ? 'text-white' : 'text-demo-blue-100 group-hover:text-white'
                  ]"
                />
                <!-- Glow effect cuando está activo -->
                <div 
                  v-if="isActive || isAlertPageForMissionVisits(item.to)" 
                  class="absolute inset-0 bg-white/20 rounded-lg blur-md -z-10"
                ></div>
              </div>
              
              <!-- Label (solo cuando está expandido) -->
              <Transition
                enter-active-class="transition-all duration-200"
                enter-from-class="opacity-0 translate-x-2"
                leave-active-class="transition-all duration-150"
                leave-to-class="opacity-0 translate-x-2"
              >
                <span v-if="!collapsed" class="text-sm font-medium flex-1">
                  {{ item.label }}
                </span>
              </Transition>
              
              <!-- Notification badge (solo para visitas activas y cuando hay número) -->
              <Transition
                enter-active-class="transition-all duration-200"
                enter-from-class="opacity-0 scale-75"
                leave-active-class="transition-all duration-150"
                leave-to-class="opacity-0 scale-75"
              >
                <div 
                  v-if="item.to === '/visits' && stats.activeVisits > 0 && !collapsed" 
                  class="ml-auto px-2 py-0.5 bg-demo-green-400 text-gray-900 rounded-full text-xs font-bold shadow-sm"
                >
                  {{ stats.activeVisits }}
                </div>
                <!-- Badge para Visitas Misionales -->
                <div
                  v-else-if="item.to === '/mission-visits' && (stats.activeMissionVisits > 0) && !collapsed"
                  class="ml-auto px-2 py-0.5 bg-violet-300 text-gray-900 rounded-full text-xs font-bold shadow-sm"
                >
                  {{ stats.activeMissionVisits }}
                </div>
              </Transition>
              
              <!-- Tooltip para modo colapsado -->
              <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-150"
                leave-to-class="opacity-0"
              >
                <div 
                  v-if="collapsed && false" 
                  class="tooltip-sidebar"
                >
                  {{ item.label }}
                </div>
              </Transition>
            </a>
          </router-link>
        </div>
      </div>
    </nav>

    <!-- Quick Actions Section -->
    <div class="border-t border-demo-blue-800/30 px-3 py-4">
      <Transition
        enter-active-class="transition-all duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition-all duration-150"
        leave-to-class="opacity-0"
        mode="out-in"
      >
        <!-- Expanded Actions -->
        <div v-if="!collapsed" class="space-y-2">
          <div class="px-3 mb-3">
            <span class="text-xs font-semibold text-demo-blue-100 uppercase tracking-wider">
              Acciones Rápidas
            </span>
          </div>
          
          <!-- Nueva Visita Button -->
          <button 
            v-if="canCreateVisit"
            @click="$router.push('/crear-visitas')"
            class="w-full flex items-center gap-3 px-4 py-3 bg-gradient-to-r from-demo-green-500 to-demo-green-600 hover:from-demo-green-600 hover:to-demo-green-700 text-white rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105"
          >
            <Icon icon="mdi:plus-circle" class="w-5 h-5" />
            <span class="font-semibold text-sm">Nueva Visita</span>
          </button>
          
          <!-- Cerrar Sesión Button -->
          <button 
            @click="openLogoutConfirm"
            class="w-full flex items-center gap-3 px-4 py-2.5 text-demo-blue-100 hover:text-red-300 hover:bg-red-500/10 rounded-xl transition-all duration-200"
          >
            <Icon icon="mdi:logout" class="w-5 h-5" />
            <span class="font-medium text-sm">Cerrar Sesión</span>
          </button>
        </div>

        <!-- Collapsed Actions (solo iconos) -->
        <div v-else class="space-y-2">
          <!-- Nueva Visita Icon Button -->
          <button 
            v-if="canCreateVisit"
            @click="$router.push('/crear-visitas')"
            class="w-full p-3 bg-gradient-to-r from-demo-green-500 to-demo-green-600 hover:from-demo-green-600 hover:to-demo-green-700 text-white rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105 flex items-center justify-center"
            title="Nueva Visita"
          >
            <Icon icon="mdi:plus-circle" class="w-6 h-6" />
          </button>
          
          <!-- Cerrar Sesión Icon Button -->
          <button 
            @click="openLogoutConfirm"
            class="w-full p-3 text-demo-blue-100 hover:text-red-300 hover:bg-red-500/10 rounded-xl transition-all duration-200 flex items-center justify-center"
            title="Cerrar Sesión"
          >
            <Icon icon="mdi:logout" class="w-6 h-6" />
          </button>
        </div>
      </Transition>
    </div>

    <!-- Collapse Toggle Button - Solo visible en desktop -->
    <button
      @click="toggleCollapse"
      class="hidden md:flex absolute -right-4 top-24 w-8 h-8 bg-white hover:bg-demo-blue-50 border-2 border-demo-blue-200 rounded-full items-center justify-center shadow-lg transition-all duration-200 hover:scale-110 group"
      :title="collapsed ? 'Expandir menú' : 'Contraer menú'"
    >
      <Icon
        :icon="collapsed ? 'mdi:chevron-right' : 'mdi:chevron-left'"
        class="w-5 h-5 text-demo-blue-600 group-hover:text-demo-blue-700 transition-colors"
      />
    </button>
  </aside>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import AuthService from '@/services/AuthService';
import MicrosoftAuthService from '@/services/MicrosoftAuthService';
import { Icon } from '@iconify/vue';
import { MENU } from '@/menu';
import { LogoInstitucionDemo } from '@/components/UI';
import logger from '../../utils/logger';
import Swal from 'sweetalert2';

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({ activeVisits: 0, todayVisitors: 0, totalVisitors: 0 })
  },
  mobileOpen: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['toggle', 'closeMobile']);

const router = useRouter();
const route = useRoute();

// Estado del sidebar: persistido en localStorage
const collapsed = ref(true);

// Cargar estado del sidebar desde localStorage
onMounted(() => {
  const savedState = localStorage.getItem('sidebar-collapsed');
  if (savedState !== null) {
    collapsed.value = savedState === 'true';
  }
});

// Get user role from localStorage
const getUserRole = () => {
  try {
    const userData = localStorage.getItem('user');
    if (userData) {
      const user = JSON.parse(userData);
      return user.role || 'Guardia';
    }
    return localStorage.getItem('role') || 'Guardia';
  } catch {
    return 'Guardia';
  }
};

const userRole = ref(getUserRole());

// Filtrar menú por rol del usuario
const visibleMenuItems = computed(() => {
  const role = userRole.value;
  return MENU.filter(item => {
    if (!item.roles) return true;
    return item.roles.includes(role);
  });
});

// Check if user can create visits
const canCreateVisit = computed(() => {
  return ['Admin', 'Asist_adm'].includes(userRole.value);
});

// Clases dinámicas del sidebar
const sidebarClasses = computed(() => {
  const baseClasses = [];
  
  // Width classes
  if (collapsed.value) {
    baseClasses.push('w-[72px]');
  } else {
    baseClasses.push('w-[280px]');
  }
  
  // Mobile behavior - show as overlay when mobileOpen is true
  if (props.mobileOpen) {
    baseClasses.push('flex md:flex z-50');
  } else {
    baseClasses.push('hidden md:flex');
  }
  
  return baseClasses;
});

// Toggle collapse state
const toggleCollapse = () => {
  collapsed.value = !collapsed.value;
  // Guardar estado en localStorage
  localStorage.setItem('sidebar-collapsed', collapsed.value.toString());
  emit('toggle', collapsed.value);
};

// Navigate and manage sidebar state
const navigateAndCollapse = (path) => {
  router.push(path);
  // Cerrar sidebar móvil después de navegar
  if (props.mobileOpen) {
    emit('closeMobile');
  }
};

// Check if current page is an alert page for mission visits
const isAlertPageForMissionVisits = (menuPath) => {
  if (menuPath !== '/mission-visits') return false;
  return route.path.includes('/alert');
};

// Logout handler
const performLogout = async () => {
  try {
    try { await AuthService.logout(); } catch (e) { console.warn('Backend logout failed', e); }
    try { await MicrosoftAuthService.logoutMicrosoft(); } catch (e) { console.warn('Microsoft logout failed', e); }
    try { await AuthService.clearClientStorage(); } catch (e) { console.warn('clearClientStorage failed', e); }
    await router.push('/');
  } catch (error) {
    logger.error('Error al cerrar sesión', error);
    Swal.fire({
      title: 'Error al Cerrar Sesión',
      text: 'No se pudo completar el cierre de sesión. Por favor intente nuevamente.',
      icon: 'error',
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#DC2626',
      customClass: {
        popup: 'rounded-2xl',
        title: 'text-2xl font-bold',
        htmlContainer: 'text-gray-600',
        confirmButton: 'rounded-xl px-6 py-3 font-semibold shadow-lg hover:shadow-xl'
      }
    });
  }
};

const openLogoutConfirm = async () => {
  const result = await Swal.fire({
    title: '¿Cerrar Sesión?',
    html: '<p class="text-gray-600 leading-relaxed">Serás redirigido a la pantalla de inicio de sesión.</p>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, Cerrar Sesión',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#DC2626',
    cancelButtonColor: '#6B7280',
    reverseButtons: true,
    customClass: {
      popup: 'rounded-2xl shadow-2xl',
      title: 'text-2xl font-bold text-gray-900',
      htmlContainer: 'text-base',
      confirmButton: 'rounded-xl px-6 py-3 font-semibold shadow-lg hover:shadow-xl hover:scale-105 transition-all',
      cancelButton: 'rounded-xl px-6 py-3 font-semibold shadow-md hover:shadow-lg hover:scale-105 transition-all'
    },
    buttonsStyling: true
  });
  
  if (result.isConfirmed) {
    await performLogout();
  }
};
</script>

<style scoped>
/* Transición suave del sidebar */
.sidebar-transition {
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Tooltip para items en modo colapsado */
.tooltip-sidebar {
  position: absolute;
  left: 100%;
  top: 50%;
  transform: translateY(-50%);
  margin-left: 12px;
  padding: 8px 12px;
  background-color: rgba(17, 24, 39, 0.95);
  color: white;
  font-size: 0.75rem;
  font-weight: 500;
  border-radius: 8px;
  white-space: nowrap;
  pointer-events: none;
  z-index: 60;
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}

.tooltip-sidebar::before {
  content: '';
  position: absolute;
  right: 100%;
  top: 50%;
  transform: translateY(-50%);
  border: 6px solid transparent;
  border-right-color: rgba(17, 24, 39, 0.95);
}

/* Animación de entrada para el sidebar */
@keyframes slideIn {
  from {
    transform: translateX(-100%);
  }
  to {
    transform: translateX(0);
  }
}

/* Scrollbar personalizado para navegación */
.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.3);
}
</style>
