<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from "vue";
import VisitService from "@/services/VisitService";
import AppLayout from "../components/layouts/AppLayout.vue";
import VisitModal from "@/components/modals/VisitModal.vue";
import Swal from 'sweetalert2';
import { Icon } from '@iconify/vue';
import logger from '../utils/logger';
import { 
  AppButton,
  Skeleton
} from '@/components/UI';
import { useStats } from '@/composables/useStats';
import { useRouter } from 'vue-router';
import { getDocumentType, getIdentityNumber, getFormattedDocument, formatTime } from '@/utils/visitFormatters';

// Composable para manejar estadísticas según el rol
const { stats, loadHeaderStats, loadMissionVisitsStats } = useStats();

const router = useRouter();

const isLoadingVisits = ref(false);

// Auto-refresh configuration
const autoRefreshInterval = ref(null);
const AUTO_REFRESH_TIME = 60000; // 60 segundos

const search = ref("");
const visits = ref([]);
const selectedVisit = ref(null);

const showCloseModal = ref(false);
const isClosingVisit = ref(false);

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

// Paginación
const currentPage = ref(1);
const itemsPerPage = 15;

/**
 * Maneja la actualización de la placa de vehículo desde el modal
 */
const handleVehicleUpdated = (updatedVisit) => {
  const index = visits.value.findIndex(v => v.id === updatedVisit.id);
  if (index !== -1) {
    visits.value[index].vehicle_plate = updatedVisit.vehicle_plate;
  }
  
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
  if (isClosingVisit.value) {
    logger.warn('Ya hay un cierre de visita en proceso');
    return;
  }

  errorModalMessage.value = "";
  isClosingVisit.value = true;

  try {
    const payload = vehicle_plate ? { vehicle_plate } : {};
    await VisitService.closeVisit(visit.id, payload);

    selectedVisit.value = null;
    showCloseModal.value = false;
    
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
    
    await fetchVisits(false, true);
  } catch (err) {
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
    isClosingVisit.value = false;
  }
};

// Obtiene las visitas activas misionales del servidor
const fetchVisits = async (silent = false, includeStats = false) => {
  if (!silent) {
    isLoadingVisits.value = true;
    currentPage.value = 1;
  }

  try {
    const query = search.value?.trim();
    const data = await VisitService.getActiveMissionVisits(query);

    visits.value = data;
    
    if (includeStats) {
      await loadStats();
    }
    
  } catch (error) {
    logger.error('Error al cargar visitas activas misionales', error);
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

const startAutoRefresh = () => {
  if (autoRefreshInterval.value) {
    clearInterval(autoRefreshInterval.value);
  }

  autoRefreshCycles = 0;
  logger.log('Auto-refresh activado para visitas activas misionales (60s)');
  
  autoRefreshInterval.value = setInterval(() => {
    autoRefreshCycles++;
    const includeStats = autoRefreshCycles % 2 === 0;
    fetchVisits(true, includeStats);
  }, AUTO_REFRESH_TIME);
};

const stopAutoRefresh = () => {
  if (autoRefreshInterval.value) {
    clearInterval(autoRefreshInterval.value);
    autoRefreshInterval.value = null;
    logger.log('Auto-refresh detenido');
  }
};

onMounted(() => {
  fetchVisits(false, true);
  startAutoRefresh();
});

onBeforeUnmount(() => {
  stopAutoRefresh();
});

const openVisitModal = (visit, action) => {
  selectedVisit.value = visit;
  
  if (action === 'view' || visit.status_id !== 1) {
    selectedVisit.value.viewOnly = true;
  } else {
    selectedVisit.value.viewOnly = false;
  }
  
  showCloseModal.value = true;
}

/**
 * Navegar a la vista de registro de alerta
 */
const openAlertRegister = (visit) => {
  if (!visit?.visitors || !Array.isArray(visit.visitors) || visit.visitors.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Sin Visitante',
      text: 'Esta visita no tiene visitantes registrados.',
      confirmButtonColor: '#dc2626',
    });
    return;
  }

  const visitor = visit.visitors[0];
  
  // Verificar si ya tiene alerta registrada
  if (visitor?.has_alert && visitor?.case_id) {
    Swal.fire({
      icon: 'warning',
      title: 'Alerta Ya Registrada',
      text: `Esta visita ya tiene una alerta registrada. No puedes crear otra alerta para la misma visita.`,
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#3b82f6',
    });
    return;
  }

  router.push({
    name: 'alert-register',
    params: {
      visitId: visit.id,
      visitorId: visitor.id
    }
  });
};

// Estadísticas para las tarjetas (Solo casos misionales - FIJO para todos los roles)
const statsCards = ref({
  todayVisitors: 0,
  activeVisits: 0,
  totalVisitors: 0
});

// Carga las estadísticas - FIJO para visitas misionales
const loadStats = async () => {
  try {
    // Cargar estadísticas del header (badges superiores según rol)
    await loadHeaderStats();
    
    // Cargar estadísticas FIJAS para tarjetas (solo misionales para TODOS)
    const cardsData = await loadMissionVisitsStats();
    
    if (cardsData) {
      statsCards.value = {
        todayVisitors: cardsData.today_visitors || 0,
        activeVisits: cardsData.active_visits || 0,
        totalVisitors: cardsData.total_visitors_this_week || 0
      };
    }
  } catch (error) {
    logger.error('Error al cargar estadísticas de casos misionales', error);
  }
};
</script>

<template>
  <AppLayout :stats="stats">
    <div class="max-w-7xl mx-auto space-y-8">
      <!-- Page Header -->
      <div class="mb-6">
        <div class="flex items-center gap-3">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Visitas Activas Misionales</h1>
            <p class="text-gray-600 text-sm mt-1">
              Gestión de casos misionales en tiempo real
            </p>
          </div>
        </div>
      </div>

      <!-- Stats Cards - Casos Misionales -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="relative overflow-hidden bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl p-6 border border-purple-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-purple-700">Total Semanal</p>
              <p class="text-3xl font-bold text-purple-900 mt-1">{{ statsCards.totalVisitors }}</p>
            </div>
            <div class="w-14 h-14 bg-purple-200 rounded-xl flex items-center justify-center">
              <Icon icon="mdi:chart-timeline-variant" class="w-7 h-7 text-purple-600" />
            </div>
          </div>
          <div class="absolute -bottom-2 -right-2 w-16 h-16 bg-purple-200 rounded-full opacity-30"></div>
        </div>

        <div class="relative overflow-hidden bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-2xl p-6 border border-indigo-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-indigo-700">Cerradas Hoy</p>
              <p class="text-3xl font-bold text-indigo-900 mt-1">{{ statsCards.todayVisitors }}</p>
            </div>
            <div class="w-14 h-14 bg-indigo-200 rounded-xl flex items-center justify-center">
              <Icon icon="mdi:check-circle" class="w-7 h-7 text-indigo-600" />
            </div>
          </div>
          <div class="absolute -bottom-2 -right-2 w-16 h-16 bg-indigo-200 rounded-full opacity-30"></div>
        </div>

        <div class="relative overflow-hidden bg-gradient-to-br from-violet-50 to-violet-100 rounded-2xl p-6 border border-violet-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-violet-700">Visitas Activas</p>
              <p class="text-3xl font-bold text-violet-900 mt-1">{{ statsCards.activeVisits }}</p>
            </div>
            <div class="w-14 h-14 bg-violet-200 rounded-xl flex items-center justify-center">
              <Icon icon="mdi:briefcase-clock" class="w-7 h-7 text-violet-600" />
            </div>
          </div>
          <div class="absolute -bottom-2 -right-2 w-16 h-16 bg-violet-200 rounded-full opacity-30"></div>
        </div>
      </div>

      <!-- Search and Filters Panel -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
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
                class="block w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all text-sm"
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
        <div class="px-6 py-4 border-b border-gray-100 bg-purple-50">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <h2 class="text-lg font-semibold text-gray-900">Casos Misionales en Curso</h2>
              <div class="px-3 py-1 bg-purple-200 text-purple-900 rounded-full text-sm font-medium">
                {{ visits.length }} resultados
              </div>
            </div>
            
            <!-- Real-time indicator -->
            <div class="flex items-center gap-2 text-sm text-purple-600">
              <div class="w-2 h-2 bg-purple-500 rounded-full animate-pulse"></div>
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
            <div class="w-24 h-24 mx-auto mb-6 bg-purple-100 rounded-full flex items-center justify-center">
              <Icon icon="mdi:briefcase-outline" class="w-12 h-12 text-purple-400" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay casos misionales activos</h3>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">
              No hay visitas misionales activas en este momento
            </p>
          </div>
        </div>

        <!-- Data Table -->
        <div v-else class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-purple-50 border-b border-purple-200">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-purple-900 uppercase tracking-wider">
                  Visitante
                </th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-purple-900 uppercase tracking-wider">
                  Documento
                </th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-purple-900 uppercase tracking-wider">
                  Estado
                </th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-purple-900 uppercase tracking-wider">
                  Horarios
                </th>

                <th class="px-6 py-4 text-left text-xs font-semibold text-purple-900 uppercase tracking-wider">
                  Departamento
                </th>
                <th class="px-6 py-4 text-center text-xs font-semibold text-purple-900 uppercase tracking-wider">
                  Acciones
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="visit in paginatedVisits" :key="visit.id" class="hover:bg-purple-50 transition-colors duration-150">
                <!-- Visitante -->
                <td class="px-6 py-4">
                  <div v-if="visit.visitors && visit.visitors.length > 0" class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                      <Icon icon="mdi:account" class="w-5 h-5 text-purple-600" />
                    </div>
                    <div>
                      <div class="text-sm font-semibold text-gray-900">
                        {{ visit.visitors[0].name }} {{ visit.visitors[0].lastName }}
                      </div>
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
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    <div class="w-1.5 h-1.5 bg-purple-500 rounded-full"></div>
                    Activa
                  </span>
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
                      class="p-2 text-purple-600 hover:text-purple-700 hover:bg-purple-50 rounded-lg transition-all duration-150"
                      title="Ver detalles"
                    >
                      <Icon icon="mdi:eye" class="w-5 h-5" />
                    </button>
                    
                    <button
                      @click="openAlertRegister(visit)"
                      :class="[
                        'p-2 rounded-lg transition-all duration-150 relative',
                        visit.has_alert 
                          ? 'text-green-600 hover:text-green-700 hover:bg-green-50' 
                          : 'text-red-600 hover:text-red-700 hover:bg-red-50'
                      ]"
                      :title="visit.has_alert ? 'Ver alerta registrada' : 'Registrar alerta'"
                    >
                      <Icon 
                        :icon="visit.has_alert ? 'mdi:check-circle' : 'mdi:alert-circle'" 
                        class="w-5 h-5" 
                      />
                      <span 
                        v-if="visit.has_alert"
                        class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-white"
                      ></span>
                    </button>
                    
                    <button
                      v-if="visit.status_id === 1"
                      @click="openVisitModal(visit, 'close')"
                      class="p-2 text-gray-600 hover:text-gray-700 hover:bg-gray-50 rounded-lg transition-all duration-150"
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
                      ? 'bg-purple-600 text-white'
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
        @vehicle-updated="handleVehicleUpdated"
      />
    </div>
  </AppLayout>
</template>
