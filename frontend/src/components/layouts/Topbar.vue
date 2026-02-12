<template>
  <header 
    class="topbar-transition fixed top-0 right-0 h-16 bg-white border-b border-slate-100 z-30 shadow-sm" 
    :style="{ left: sidebarWidth }"
  >
    <div class="h-full px-6 flex items-center">
      <!-- Left Section: Brand Chip (only when sidebar collapsed) -->
      <div class="flex-1">
        <Transition
          enter-active-class="transition-all duration-300"
          enter-from-class="opacity-0 -translate-x-2"
          leave-active-class="transition-all duration-200"
          leave-to-class="opacity-0 -translate-x-2"
        >
          <div 
            v-if="sidebarCollapsed"
            class="hidden md:inline-flex items-center gap-1 px-3 h-9 rounded-full text-slate-700 font-medium tracking-wide hover:bg-slate-50 transition-colors cursor-pointer"
            @click="$router.push('/visits')"
          >
            <span class="font-semibold">Institución Demo</span>
            <span class="text-slate-400">·</span>
            <span>Sistema de Visitas</span>
          </div>
        </Transition>
        
        <!-- Mobile Menu Button (left side when sidebar not present on mobile) -->
        <button 
          @click="$emit('toggleMobileSidebar')"
          class="md:hidden p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-all duration-200 focus-visible:outline-2 focus-visible:outline-demo-blue-500"
          aria-label="Abrir menú"
        >
          <Icon icon="mdi:menu" class="w-6 h-6" />
        </button>
      </div>

      <!-- Center Section: Search (always centered) -->
      <div class="flex-1 flex justify-center">
        <button
          @click="openCommandPalette"
          class="w-full max-w-xl flex items-center gap-3 px-4 py-2 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition-all duration-200 focus-visible:ring-2 focus-visible:ring-demo-blue-500/50"
          aria-label="Abrir búsqueda global"
        >
          <Icon icon="mdi:magnify" class="w-4 h-4 text-gray-400" />
          <span class="text-sm text-gray-500 flex-1 text-left">Buscar...</span>
          <kbd class="flex items-center gap-0.5 px-1.5 py-0.5 bg-white border border-gray-300 rounded text-xs font-medium text-gray-600">
            <span class="text-xs">CTRL + K</span>
          </kbd>
        </button>
      </div>

      <!-- Right Section: Stats Pill + Avatar -->
      <div class="flex-1 flex justify-end items-center gap-3">
        <!-- Quick Stats Pill -->
        <div class="hidden sm:flex items-center gap-2 px-3 h-9 rounded-full text-xs text-slate-600 bg-slate-50 border border-slate-200">
          <div class="flex items-center gap-1">
            <div class="w-1.5 h-1.5 bg-demo-blue-500 rounded-full"></div>
            <span class="font-medium">{{ stats.todayVisitors }}</span>
            <span>hoy</span>
          </div>
          <div class="w-px h-3 bg-slate-300"></div>
          <div class="flex items-center gap-1">
            <div class="w-1.5 h-1.5 bg-demo-green-500 rounded-full animate-pulse"></div>
            <span class="font-medium">{{ stats.activeVisits }}</span>
            <span>activas</span>
          </div>
        </div>

        <!-- User Menu -->
        <div class="relative">
          <button 
            @click="toggleUserMenu"
            class="flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded-lg transition-all duration-200 focus-visible:ring-2 focus-visible:ring-demo-blue-500/50"
            :class="{ 'bg-gray-50': userMenuOpen }"
            aria-label="Menú de usuario"
            :aria-expanded="userMenuOpen"
          >
            <!-- Avatar -->
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-demo-blue-500 to-demo-blue-700 flex items-center justify-center text-white font-semibold text-xs shadow-sm">
              {{ userInitials }}
            </div>
          </button>
          
          <!-- User Menu Dropdown -->
          <Transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0 scale-95 -translate-y-2"
            leave-active-class="transition-all duration-150"
            leave-to-class="opacity-0 scale-95 -translate-y-2"
          >
            <div 
              v-if="userMenuOpen" 
              class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden"
            >
              <!-- User Info Header -->
              <div class="p-4 bg-gradient-to-br from-demo-blue-50 to-demo-blue-100 border-b border-demo-blue-200">
                <div class="flex items-center gap-3">
                  <div class="w-12 h-12 rounded-full bg-gradient-to-br from-demo-blue-500 to-demo-blue-700 flex items-center justify-center text-white font-semibold shadow-md">
                    {{ userInitials }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-gray-900 truncate">{{ userName }}</div>
                    <div class="text-xs text-demo-blue-700 font-medium">{{ userRole }}</div>
                    <div class="text-xs text-gray-600 truncate mt-0.5">{{ userEmail }}</div>
                  </div>
                </div>
              </div>
              
              <!-- Menu Items -->
              <div class="p-2">
                <!-- Cerrar Sesión -->
                <button
                  @click="openLogoutConfirm"
                  class="w-full flex items-center gap-3 px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200 font-medium focus-visible:ring-2 focus-visible:ring-red-500/50"
                >
                  <Icon icon="mdi:logout" class="w-4 h-4" />
                  <span>Cerrar Sesión</span>
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Icon } from '@iconify/vue';
import AuthService from '@/services/AuthService';
import MicrosoftAuthService from '@/services/MicrosoftAuthService';
import logger from '../../utils/logger';
import Swal from 'sweetalert2';

const props = defineProps({
  sidebarCollapsed: {
    type: Boolean,
    default: true
  },
  stats: {
    type: Object,
    default: () => ({ todayVisitors: 0, activeVisits: 0, totalVisitors: 0 })
  }
});

const emit = defineEmits(['openCommandPalette', 'toggleMobileSidebar']);

const route = useRoute();
const router = useRouter();

const userMenuOpen = ref(false);

// Computed: Ancho del sidebar
const sidebarWidth = computed(() => props.sidebarCollapsed ? '72px' : '280px');

// Computed: Información del usuario
const user = computed(() => {
  try {
    const userData = localStorage.getItem('user');
    return userData ? JSON.parse(userData) : null;
  } catch {
    return null;
  }
});

const userInitials = computed(() => {
  if (!user.value || !user.value.name) return 'U';
  const names = user.value.name.split(' ');
  if (names.length >= 2) {
    return (names[0][0] + names[1][0]).toUpperCase();
  }
  return user.value.name.substring(0, 2).toUpperCase();
});

const userName = computed(() => user.value?.name || 'Usuario');
const userRole = computed(() => {
  const roleNames = {
    'Admin': 'Administrador',
    'Asist_adm': 'Asistente Administrativo',
    'Guardia': 'Guardia de Seguridad',
    'Solicitante': 'Solicitante'
  };
  return roleNames[user.value?.role] || user.value?.role || 'Usuario';
});
const userEmail = computed(() => user.value?.email || 'correo@demo.example.org');

// Methods
const openCommandPalette = () => {
  emit('openCommandPalette');
};

const toggleUserMenu = () => {
  userMenuOpen.value = !userMenuOpen.value;
};

const performLogout = async () => {
  try {
    // 1. Attempt backend logout (non-blocking)
    try { await AuthService.logout(); } catch (e) { console.warn('Backend logout failed', e); }

    // 2. Try to log out from Microsoft (best-effort)
    try { await MicrosoftAuthService.logoutMicrosoft(); } catch (e) { console.warn('Microsoft logout failed', e); }

    // 3. Clear all client storage
    try { await AuthService.clearClientStorage(); } catch (e) { console.warn('clearClientStorage failed', e); }

    // Close user menu
    userMenuOpen.value = false;

    // Redirect to login
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
  userMenuOpen.value = false;
  
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

// Cerrar menú de usuario al hacer clic fuera
const handleClickOutside = (event) => {
  const userMenu = event.target.closest('.relative');
  if (!userMenu && userMenuOpen.value) {
    userMenuOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
/* Transición suave de la topbar cuando el sidebar cambia */
.topbar-transition {
  transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Asegurar que el backdrop blur funcione bien en todos los navegadores */
@supports (backdrop-filter: blur(12px)) {
  header {
    backdrop-filter: blur(12px);
  }
}

@supports not (backdrop-filter: blur(12px)) {
  header {
    background-color: rgba(255, 255, 255, 0.95);
  }
}
</style>
