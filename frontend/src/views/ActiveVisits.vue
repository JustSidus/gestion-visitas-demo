<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed } from "vue";
import VisitService from "@/services/VisitService";
import AppLayout from "../components/layouts/AppLayout.vue";
import { useRouter, useRoute } from 'vue-router';
import VisitModal from "@/components/modals/VisitModal.vue";
import Swal from 'sweetalert2';
import { Icon } from '@iconify/vue';
import logger from '../utils/logger';
import { 
  AppButton,
  Skeleton
} from '@/components/UI';
import { useStats } from '@/composables/useStats';
import { getDocumentType, getIdentityNumber, getFormattedDocument, formatTime } from '@/utils/visitFormatters';

// Composable para manejar estadísticas según el rol
const { stats, loadHeaderStats, loadActiveVisitsStats } = useStats();

const router = useRouter();
const route = useRoute();

const isLoadingVisits = ref(false);

// Auto-refresh configuration
const autoRefreshInterval = ref(null);
const AUTO_REFRESH_TIME = 60000; // 60 segundos (optimizado)

const search = ref("");
const visits = ref([]);
const selectedVisit = ref(null);

const showCloseModal = ref(false);
const isClosingVisit = ref(false); // Bandera para prevenir múltiples clics

const errorModalMessage = ref("");

// Obtener rol del usuario
const user = computed(() => {
  try {
    const userData = localStorage.getItem('user');
    return userData ? JSON.parse(userData) : null;
  } catch {
    return null;
  }
});

const userRole = computed(() => user.value?.role || null);

// Verificar si el usuario puede crear visitas
const canCreateVisit = computed(() => {
  return ['Admin', 'Asist_adm'].includes(userRole.value);
});

// Paginación
const currentPage = ref(1);
const itemsPerPage = 15;

/**
 * Maneja la actualización de la placa de vehículo desde el modal
 * Actualiza la visita en la lista sin recargar todo
 */
const handleVehicleUpdated = (updatedVisit) => {
  const index = visits.value.findIndex(v => v.id === updatedVisit.id);
  if (index !== -1) {
    visits.value[index].vehicle_plate = updatedVisit.vehicle_plate;
  }
  
  // Mostrar notificación
  Swal.fire({
    position: 'top-end',
    icon: 'success',
    title: 'Placa actualizada correctamente',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true,
    toast: true,
    customClass: {
      popup: 'rounded-xl shadow-lg bg-white text-sm px-4 py-3 border border-green-300',
      title: 'text-green-800 font-medium',
      icon: 'text-green-500',
      timerProgressBar: 'bg-green-400',
    },
  });
};

const confirmCloseVisit = async ({ visit, vehicle_plate }) => {
  // Prevenir múltiples clics
  if (isClosingVisit.value) {
    logger.warn('Ya hay un cierre de visita en proceso');
    return;
  }

  errorModalMessage.value = ""; // Limpia errores previos
  isClosingVisit.value = true; // Activar bandera de procesamiento

  try {
    // Enviar la placa de vehículo si existe
    const payload = vehicle_plate ? { vehicle_plate } : {};
    await VisitService.closeVisit(visit.id, payload);

    selectedVisit.value = null;
    showCloseModal.value = false;
    
    // Mostrar notificación con SweetAlert2
    Swal.fire({
      position: 'top-end',
      icon: 'success',
      title: 'Visita cerrada exitosamente',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      toast: true,
      customClass: {
        popup: 'rounded-xl shadow-lg bg-white text-sm px-4 py-3 border border-green-300',
        title: 'text-green-800 font-medium',
        icon: 'text-green-500',
        timerProgressBar: 'bg-green-400',
      },
    });
    
    await fetchVisits(false, true); // Cargar visitas e incluir estadísticas después de cerrar
  } catch (err) {
    // Mostrar error específico si es de restricción de horario
    if (err.response?.status === 403) {
      errorModalMessage.value = err.response?.data?.error || "No tienes permisos para realizar esta acción en este momento.";
      Swal.fire({
        icon: 'error',
        title: 'Restricción de horario',
        text: err.response?.data?.error,
        confirmButtonColor: '#3085d6'
      });
    } else {
      errorModalMessage.value = err.response?.data?.message || "Ocurrió un error al cerrar la visita.";
    }
  } finally {
    isClosingVisit.value = false; // Liberar bandera de procesamiento
  }
};

// Obtiene las visitas activas del servidor
// silent: si es true, no muestra spinner de carga
// includeStats: si es true, actualiza también las estadísticas (solo en búsquedas manuales)
const fetchVisits = async (silent = false, includeStats = false) => {
  if (!silent) {
    isLoadingVisits.value = true;
    currentPage.value = 1;
  }

  try {
    const query = search.value?.trim();
    
    // Si el rol es Asist_adm, solo obtener visitas NO misionales
    let data;
    if (userRole.value === 'Asist_adm') {
      data = await VisitService.getActiveNonMissionVisits(query);
    } else {
      // Para Admin y Guardia, mostrar todas las visitas activas
      data = await VisitService.getActiveVisits(query);
    }

    visits.value = data;
    
    // Cargar estadísticas solo cuando se necesita (búsquedas manuales, no en auto-refresh)
    if (includeStats) {
      await loadStats();
    }
    
  } catch (error) {
    logger.error('Error al cargar visitas activas', error);
  } finally {
    if (!silent) {
      isLoadingVisits.value = false;
    }
  }
};

// Computed para datos paginados
const paginatedVisits = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return visits.value.slice(start, end);
});

const totalPages = computed(() => Math.ceil(visits.value.length / itemsPerPage));

const changePage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
};

// Variable para contar ciclos de auto-refresh
let autoRefreshCycles = 0;

// Inicia el auto-refresh que actualiza visitas cada 60 segundos
// Actualiza estadísticas cada 2 ciclos (120 segundos) para reducir carga del servidor
const startAutoRefresh = () => {
  if (autoRefreshInterval.value) {
    clearInterval(autoRefreshInterval.value);
  }

  autoRefreshCycles = 0;
  logger.log('Auto-refresh activado para visitas activas (60s)');
  
  autoRefreshInterval.value = setInterval(() => {
    autoRefreshCycles++;
    // Actualiza estadísticas cada 2 ciclos (120 segundos)
    const includeStats = autoRefreshCycles % 2 === 0;
    fetchVisits(true, includeStats);
  }, AUTO_REFRESH_TIME);
};

// Detiene el auto-refresh
const stopAutoRefresh = () => {
  if (autoRefreshInterval.value) {
    clearInterval(autoRefreshInterval.value);
    autoRefreshInterval.value = null;
    logger.log('Auto-refresh detenido');
  }
};

onMounted(() => {
  fetchVisits(false, true); // Cargar visitas e incluir estadísticas en carga inicial
  startAutoRefresh();
});

// Limpiar intervalo al desmontar componente
onBeforeUnmount(() => {
  stopAutoRefresh();
});

const openVisitModal = (visit, action) => {
  selectedVisit.value = visit;
  
  // Si la acción es ver o si la vista está cerrada (status_id !== 1), solo mostramos en modo vista
  if (action === 'view' || visit.status_id !== 1) {
    selectedVisit.value.viewOnly = true;
  } else {
    selectedVisit.value.viewOnly = false;
  }
  
  showCloseModal.value = true;
}

const createVisit = () => {
  return router.push({name: "crear-visitas"})
};

// Carga las estadísticas para los badges superiores y tarjetas
const loadStats = async () => {
  try {
    // Cargar estadísticas del header (badges superiores)
    await loadHeaderStats();
    
    // Cargar estadísticas para las tarjetas grandes según el rol
    const cardsData = await loadActiveVisitsStats();
    
    if (cardsData) {
      // Actualizar las tarjetas grandes con los datos específicos del rol
      stats.value.totalVisitors = cardsData.total_visitors_this_week || 0;
      stats.value.todayVisitors = cardsData.today_visitors || 0;
      stats.value.activeVisits = cardsData.active_visits || 0;
    }
  } catch (error) {
    logger.error('Error al cargar estadísticas', error);
  }
};
</script>

<template>
  <AppLayout :stats="stats">
    <div class="max-w-7xl mx-auto space-y-8">
      <!-- Page Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Visitas Activas</h1>
        <p class="text-gray-600 text-sm mt-1">
          Monitorea las visitas en curso en tiempo real
        </p>
        
        <!-- Actions for Active Visits -->
        <div class="flex items-center gap-3 mt-4">
          <AppButton
            v-if="canCreateVisit"
            variant="primary"
            icon-left="mdi:plus"
            @click="createVisit"
          >
            Nueva Visita
          </AppButton>
        </div>
      </div>

      <!-- Stats Cards - Only for Admin and Asist_adm -->
      <div v-if="userRole !== 'Guardia'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="relative overflow-hidden bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-blue-700">Total Semanal</p>
              <p class="text-3xl font-bold text-blue-900 mt-1">{{ stats.totalVisitors }}</p>
            </div>
            <div class="w-14 h-14 bg-blue-200 rounded-xl flex items-center justify-center">
              <Icon icon="mdi:chart-timeline-variant" class="w-7 h-7 text-blue-600" />
            </div>
          </div>
          <div class="absolute -bottom-2 -right-2 w-16 h-16 bg-blue-200 rounded-full opacity-30"></div>
        </div>

        <div class="relative overflow-hidden bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-6 border border-green-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-green-700">Cerradas Hoy</p>
              <p class="text-3xl font-bold text-green-900 mt-1">{{ stats.todayVisitors }}</p>
            </div>
            <div class="w-14 h-14 bg-green-200 rounded-xl flex items-center justify-center">
              <Icon icon="mdi:check-circle" class="w-7 h-7 text-green-600" />
            </div>
          </div>
          <div class="absolute -bottom-2 -right-2 w-16 h-16 bg-green-200 rounded-full opacity-30"></div>
        </div>

        <div class="relative overflow-hidden bg-gradient-to-br from-orange-50 to-orange-100 rounded-2xl p-6 border border-orange-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-orange-700">Visitas Activas</p>
              <p class="text-3xl font-bold text-orange-900 mt-1">{{ stats.activeVisits }}</p>
            </div>
            <div class="w-14 h-14 bg-orange-200 rounded-xl flex items-center justify-center">
              <Icon icon="mdi:clock-outline" class="w-7 h-7 text-orange-600" />
            </div>
          </div>
          <div class="absolute -bottom-2 -right-2 w-16 h-16 bg-orange-200 rounded-full opacity-30"></div>
        </div>
      </div>

      <!-- Search and Filters Panel -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Simple Search Bar -->
        <div class="p-6 border-b border-gray-100">
          <div class="flex items-center gap-4">
            <div class="flex-1 relative">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <Icon icon="mdi:magnify" class="w-5 h-5 text-gray-400" />
              </div>
              <input
                v-model="search"
                type="text"
                placeholder="Buscar por nombre, cédula o documento..."
                class="block w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-sm"
                @keydown.enter="fetchVisits"
              />
            </div>
            
            <AppButton
              variant="primary"
              icon-left="mdi:magnify"
              @click="fetchVisits"
              size="lg"
            >
              Buscar
            </AppButton>
          </div>
        </div>
      </div>

      <!-- Results Section -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Results Header -->
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <h2 class="text-lg font-semibold text-gray-900">Visitas en Curso</h2>
              <div class="px-3 py-1 bg-primary-100 text-primary-800 rounded-full text-sm font-medium">
                {{ visits.length }} resultados
              </div>
            </div>
            
            <!-- Real-time indicator -->
            <div class="flex items-center gap-2 text-sm text-green-600">
              <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
              <span>En tiempo real</span>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoadingVisits" class="p-12">
          <div class="space-y-4">
            <Skeleton class="h-12 w-full rounded-lg" />
            <Skeleton class="h-12 w-full rounded-lg" />
            <Skeleton class="h-12 w-full rounded-lg" />
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="visits.length === 0" class="p-12">
          <div class="text-center">
            <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
              <Icon icon="mdi:inbox-outline" class="w-12 h-12 text-gray-400" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay visitas activas</h3>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">
              No hay visitas activas en este momento
            </p>
            <AppButton
              v-if="canCreateVisit"
              variant="primary"
              icon-left="mdi:plus"
              @click="createVisit"
            >
              Registrar Primera Visita
            </AppButton>
          </div>
        </div>

        <!-- Data Table -->
        <div v-else class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Visitante
                </th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Documento
                </th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Estado
                </th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Tipo
                </th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Horarios
                </th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Persona a Visitar
                </th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Departamento
                </th>
                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="visit in paginatedVisits" :key="visit.id" class="hover:bg-gray-50 transition-colors duration-150">
                <!-- Visitante -->
                <td class="px-6 py-4">
                  <div v-if="visit.visitors && visit.visitors.length > 0" class="flex items-center gap-3">
                    <div>
                      <td class="px-6 py-4">
                  <div class="text-sm font-semibold text-gray-900">
                    {{ visit.visitors[0].name }} {{ visit.visitors[0].lastName }}
                  </div>
                </td>
                    </div>
                  </div>
                  <div v-else class="text-sm text-gray-400 italic">
                    Sin visitante registrado
                  </div>
                </td>

                <!-- Documento -->
                <td class="px-6 py-4">
                  <div class="text-sm font-medium text-gray-900">{{ getFormattedDocument(visit) }}</div>
                </td>

                <!-- Estado -->
                <td class="px-6 py-4">
                  <span v-if="visit.status_id === 1" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <div class="w-1.5 h-1.5 bg-green-500 rounded-full"></div>
                    Activa
                  </span>
                  <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                    <div class="w-1.5 h-1.5 bg-gray-400 rounded-full"></div>
                    Cerrada
                  </span>
                </td>

                <!-- Tipo -->
                <td class="px-6 py-4">
                  <span v-if="visit.mission_case" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    <Icon icon="mdi:briefcase" class="w-3 h-3" />
                    Caso Misional
                  </span>
                  <span v-else class="text-sm text-gray-400">Regular</span>
                </td>

                <!-- Horarios -->
                <td class="px-6 py-4 text-sm">
                  <div class="space-y-1">
                    <div class="flex items-center gap-2">
                      <Icon icon="mdi:login" class="w-4 h-4 text-green-500" />
                      <span class="font-medium text-gray-900">{{ formatTime(visit.created_at) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <Icon icon="mdi:logout" class="w-4 h-4 text-red-500" />
                      <span class="text-gray-600">{{ visit.end_at ? formatTime(visit.end_at) : '—' }}</span>
                    </div>
                  </div>
                </td>

                <!-- Persona a Visitar -->
                <td class="px-6 py-4">
                  <div class="text-sm font-medium text-gray-900">{{ visit.namePersonToVisit || '—' }}</div>
                </td>

                <!-- Departamento -->
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2 text-sm text-gray-900">
                    <Icon icon="mdi:office-building" class="w-4 h-4 text-gray-400" />
                    <span>{{ visit.department || '—' }}</span>
                  </div>
                </td>

                <!-- Acciones -->
                <td class="px-6 py-4">
                  <div class="flex items-center justify-center gap-2">
                    <button
                      @click="openVisitModal(visit, 'view')"
                      class="p-2 text-primary-600 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-all duration-150"
                      title="Ver detalles"
                    >
                      <Icon icon="mdi:eye" class="w-5 h-5" />
                    </button>
                    
                    <button
                      v-if="visit.status_id === 1"
                      @click="openVisitModal(visit, 'close')"
                      class="p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all duration-150"
                      title="Cerrar visita"
                    >
                      <Icon icon="mdi:logout" class="w-5 h-5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="px-6 py-4 border-t border-gray-200 bg-gray-50">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Mostrando 
              <span class="font-medium">{{ (currentPage - 1) * itemsPerPage + 1 }}</span>
              a 
              <span class="font-medium">{{ Math.min(currentPage * itemsPerPage, visits.length) }}</span>
              de 
              <span class="font-medium">{{ visits.length }}</span>
              resultados
            </div>
            
            <div class="flex items-center gap-2">
              <button
                @click="changePage(currentPage - 1)"
                :disabled="currentPage === 1"
                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-150"
              >
                <Icon icon="mdi:chevron-left" class="w-4 h-4" />
              </button>
              
              <div class="flex gap-1">
                <button
                  v-for="page in Math.min(totalPages, 5)"
                  :key="page"
                  @click="changePage(page)"
                  :class="[
                    'px-3 py-2 text-sm font-medium rounded-lg transition-all duration-150',
                    page === currentPage
                      ? 'bg-primary-600 text-white'
                      : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'
                  ]"
                >
                  {{ page }}
                </button>
              </div>
              
              <button
                @click="changePage(currentPage + 1)"
                :disabled="currentPage === totalPages"
                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-150"
              >
                <Icon icon="mdi:chevron-right" class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal de Detalles de Visita -->
      <VisitModal
        v-if="showCloseModal"
        :visit="selectedVisit"
        :errorMessage="errorModalMessage"
        :isClosing="isClosingVisit"
        @close="showCloseModal = false"
        @confirm="confirmCloseVisit"
        @vehicleUpdated="handleVehicleUpdated"
      />
    </div>
  </AppLayout>
</template>
