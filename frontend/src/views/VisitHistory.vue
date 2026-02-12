<script setup>
import { ref, onMounted, computed, watch } from "vue";
import VisitService from "@/services/VisitService";
import AppLayout from "../components/layouts/AppLayout.vue";
import { useRouter, useRoute } from 'vue-router';
import VisitModal from "@/components/modals/VisitModal.vue";
import Swal from 'sweetalert2';
import { Icon } from '@iconify/vue';
import logger from '../utils/logger';
import { 
  AppButton, 
  AppInput, 
  AppSelect, 
  AppDialog, 
  AppBadge, 
  EmptyState, 
  Skeleton, 
  FormSection 
} from '@/components/UI';
import { useHistorialSearch } from '../composables/useHistorialSearch';
import { useStats } from '@/composables/useStats';
import { 
  getDocumentType, 
  getIdentityNumber, 
  getFormattedDocument, 
  formatTime,
  getVisitorFullName 
} from '@/utils/visitFormatters';

const router = useRouter();
const route = useRoute();

// Composable de búsqueda de historial
const {
  isLoadingVisits,
  visits,
  search,
  showAdvancedFilters,
  currentPage,
  filters,
  lastUpdateTime,
  paginatedVisits,
  totalPages,
  fetchVisits,
  clearFilters,
  toggleAdvancedFilters,
  changePage,
  exportToExcel,
  exportToPDF,
  itemsPerPage
} = useHistorialSearch();

// Composable para manejar estadísticas según el rol
const { stats, loadHeaderStats } = useStats();

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
    
    await fetchVisits();
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

/**
 * Maneja la exportación a Excel con feedback de usuario
 */
const handleExportToExcel = async () => {
  try {
    const result = await exportToExcel();
    if (result.success) {
      Swal.fire({
        position: 'top-end',
        icon: 'success',
        title: 'Archivo Excel descargado',
        showConfirmButton: false,
        timer: 2000,
        toast: true,
        customClass: {
          popup: 'rounded-xl shadow-lg bg-white text-sm px-4 py-3 border border-green-300',
          title: 'text-green-800 font-medium',
          icon: 'text-green-500',
          timerProgressBar: 'bg-green-400',
        },
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'No se pudo exportar el archivo',
      });
    }
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudo exportar el archivo',
    });
  }
};

/**
 * Maneja la exportación a PDF con feedback de usuario
 */
const handleExportToPDF = async () => {
  try {
    const result = await exportToPDF();
    if (result.success) {
      Swal.fire({
        position: 'top-end',
        icon: 'success',
        title: 'Archivo PDF descargado',
        showConfirmButton: false,
        timer: 2000,
        toast: true,
        customClass: {
          popup: 'rounded-xl shadow-lg bg-white text-sm px-4 py-3 border border-green-300',
          title: 'text-green-800 font-medium',
          icon: 'text-green-500',
          timerProgressBar: 'bg-green-400',
        },
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'No se pudo exportar el archivo PDF',
      });
    }
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudo exportar el archivo PDF',
    });
  }
};

onMounted(() => {
  fetchVisits();
  loadHeaderStats();
});

// Alias para mantener compatibilidad con templates existentes
const getFullName = getVisitorFullName;

// Función formatDate local que incluye hora (diferente a formatDateUtil que solo fecha)
const formatDate = (dateString) => {
  if (!dateString) return '—';
  const date = new Date(dateString);
  return date.toLocaleString("es-DO", {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  });
};

// Obtener iniciales para avatar
const getInitials = (visit) => {
  const visitor = visit.visitors?.[0];
  if (!visitor) return '?';
  const firstName = visitor.name?.charAt(0) || '';
  const lastName = visitor.lastName?.charAt(0) || '';
  return (firstName + lastName).toUpperCase() || '?';
};

// Truncar texto largo para preview
const truncateText = (text, maxLength = 50) => {
  if (!text) return '—';
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};

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
</script>

<template>
  <AppLayout :stats="stats">
    <div class="max-w-7xl mx-auto space-y-8">
      <!-- Page Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Historial de Visitas</h1>
        <p class="text-gray-600 text-sm mt-1">
          Consulta el registro completo de visitas pasadas
        </p>
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

            <!-- Advanced Filters Toggle -->
            <AppButton
              variant="ghost"
              :icon-left="showAdvancedFilters ? 'mdi:filter-minus' : 'mdi:filter-plus'"
              @click="toggleAdvancedFilters"
              size="lg"
            >
              Filtros
            </AppButton>

            <!-- Export Buttons -->            
            <AppButton
            variant="secondary"
            size="lg"
            icon-left="mdi:file-excel"
            @click="handleExportToExcel"
              >
            Excel
            </AppButton>
            
            <AppButton
            variant="secondary"
            size="lg"
            icon-left="mdi:file-pdf-box"
            @click="handleExportToPDF"
            >
              PDF
            </AppButton>
          </div>
        </div>

        <!-- Advanced Filters -->
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 max-h-0"
          enter-to-class="opacity-100 max-h-[600px]"
          leave-active-class="transition-all duration-200 ease-in"
          leave-from-class="opacity-100 max-h-[600px]"
          leave-to-class="opacity-0 max-h-0"
        >
          <div v-if="showAdvancedFilters" class="overflow-hidden border-t border-gray-100 bg-gray-50">
            <div class="p-6">
              <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                  <Icon icon="mdi:filter-variant" class="w-5 h-5 text-primary-600" />
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">Filtros Avanzados</h3>
                  <p class="text-sm text-gray-600">Refina tu búsqueda con criterios específicos</p>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Fecha Inicio</label>
                  <div class="relative">
                    <input
                      v-model="filters.start_date"
                      type="date"
                      class="block w-full pl-4 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-sm"
                    />
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Fecha Fin</label>
                  <div class="relative">
                    <input
                      v-model="filters.end_date"
                      type="date"
                      class="block w-full pl-4 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-sm"
                    />
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Persona Visitada</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                      <Icon icon="mdi:account" class="w-5 h-5 text-gray-400" />
                    </div>
                    <input
                      v-model="filters.person_visited"
                      type="text"
                      placeholder="Nombre completo"
                      class="block w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-sm"
                    />
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Departamento</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                      <Icon icon="mdi:office-building" class="w-5 h-5 text-gray-400" />
                    </div>
                    <input
                      v-model="filters.department"
                      type="text"
                      placeholder="Departamento"
                      class="block w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-sm"
                    />
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Visitante</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                      <Icon icon="mdi:account-search" class="w-5 h-5 text-gray-400" />
                    </div>
                    <input
                      v-model="filters.visitor_search"
                      type="text"
                      placeholder="Nombre o cédula"
                      class="block w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-sm"
                    />
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Tipo de Visita</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                      <Icon icon="mdi:briefcase" class="w-5 h-5 text-gray-400" />
                    </div>
                    <select
                      v-model="filters.mission_case"
                      class="block w-full pl-12 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all text-sm appearance-none"
                    >
                      <option value="all">Todos (Misionales y Regulares)</option>
                      <option value="only">Solo Casos Misionales</option>
                      <option value="exclude">Solo Casos Regulares</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                      <Icon icon="mdi:chevron-down" class="w-5 h-5 text-gray-400" />
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-4 mt-8 pt-6 border-t border-gray-200">
                <AppButton
                  variant="primary"
                  icon-left="mdi:filter-check"
                  @click="fetchVisits"
                  class="flex-shrink-0"
                >
                  Aplicar Filtros
                </AppButton>
                <AppButton
                  variant="ghost"
                  icon-left="mdi:filter-remove"
                  @click="clearFilters"
                  class="flex-shrink-0"
                >
                  Limpiar Filtros
                </AppButton>
                
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Results Section -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Results Header -->
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
          <div class="flex items-center gap-3">
            <h2 class="text-lg font-semibold text-gray-900">Registros Históricos</h2>
            <div class="px-3 py-1 bg-primary-100 text-primary-800 rounded-full text-sm font-medium">
              {{ visits.length }} resultados
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoadingVisits" class="p-12">
          <div class="flex flex-col items-center justify-center space-y-4">
            <div class="w-12 h-12 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
            <p class="text-sm text-gray-500">Cargando historial...</p>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="visits.length === 0" class="p-12">
          <div class="text-center">
            <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
              <Icon icon="mdi:inbox-outline" class="w-12 h-12 text-gray-400" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay visitas</h3>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">
              No se encontraron registros que coincidan con los criterios de búsqueda
            </p>
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
                  <div class="text-sm font-semibold text-gray-900">
                    {{ visit.visitors[0].name }} {{ visit.visitors[0].lastName }}
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
                  <span v-if="visit.mission_case" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 whitespace-nowrap">
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
                      <span class="font-medium text-gray-900">{{ formatTime(visit.created_at_raw) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <Icon icon="mdi:logout" class="w-4 h-4 text-red-500" />
                      <span class="text-gray-600">{{ visit.end_at_raw ? formatTime(visit.end_at_raw) : '—' }}</span>
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
