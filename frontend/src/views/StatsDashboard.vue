<!-- StatsDashboard.vue -->
<script setup>
import { Icon } from '@iconify/vue'
import AppLayout from '../components/layouts/AppLayout.vue';
import VisitService from '../services/VisitService';
import StatsService from '../services/StatsService';
import { ref, onMounted, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import logger from '../utils/logger';
import { getDocumentType, getIdentityNumber, getFormattedDocument, getVisitorFullName } from '@/utils/visitFormatters';

// Componentes de Gráficos
import StatsCard from '../components/stats/StatsCard.vue';
import VisitsTrendChart from '../components/stats/VisitsTrendChart.vue';
import DepartmentBarsChart from '../components/stats/DepartmentBarsChart.vue';
import VisitsDurationGauge from '../components/stats/VisitsDurationGauge.vue';
import HourlyPeakChart from '../components/stats/HourlyPeakChart.vue';
import WeekdayAverageChart from '../components/stats/WeekdayAverageChart.vue';
import WeeklyCompareChart from '../components/stats/WeeklyCompareChart.vue';

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

// Datos de Estadísticas
const isLoadingStats = ref(false);
const selectedFilter = ref('week');
const lastUpdated = ref(new Date());
const timeFilters = [
  { label: 'Semana', value: 'week', days: 7 },
  { label: 'Mes', value: 'month', days: 30 },
  { label: 'Trimestre', value: 'quarter', days: 90 },
  { label: 'Año', value: 'year', days: 365 }
];

const kpis = ref({
  today: 0,
  thisWeek: 0,
  dailyAverage: 0,
  avgDuration: 0
});

const trendData = ref({ dates: [], visits: [] });
const departmentData = ref([]);
const durationData = ref({ average: 0, min: 0, max: 0 });
const hourlyData = ref([]);
const weekdayData = ref([]);
const weeklyCompareData = ref([]);

// Estadísticas del Panel Original
const stats = ref({
  total_visitors_this_week: 0,
  today_visitors: 0,
  active_visits: 0,
  closed_visitors: 0,
  mission_cases_week: 0,
  total_visits_all_time: 0,
});

const isLoading = ref(true);

// Filtros para reportes
const reportFilters = ref({
  start_date: '',
  end_date: '',
  person_visited: '',
  visitor_search: '',
  department: '',
  assigned_carnet: '',
  mission_case: 'all' // 'all', 'only', 'exclude'
});

const isExporting = ref(false);
const isSearching = ref(false);
const previewData = ref([]);
const hasSearched = ref(false);
const showOptionalFilters = ref(false);

// Paginación
const currentPage = ref(1);
const itemsPerPage = 20;

const paginatedData = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return previewData.value.slice(start, end);
});

const totalPages = computed(() => Math.ceil(previewData.value.length / itemsPerPage));

const changePage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
};

// Validación de formulario
const isFormValid = computed(() => {
  return reportFilters.value.start_date && reportFilters.value.end_date;
});

// Texto de filtros aplicados
const appliedFiltersText = computed(() => {
  if (!hasSearched.value) return '';
  
  const parts = [];
  if (reportFilters.value.start_date) parts.push(`Desde: ${formatDate(reportFilters.value.start_date)}`);
  if (reportFilters.value.end_date) parts.push(`Hasta: ${formatDate(reportFilters.value.end_date)}`);
  if (reportFilters.value.person_visited) parts.push(`Persona: ${reportFilters.value.person_visited}`);
  if (reportFilters.value.visitor_search) parts.push(`Visitante: ${reportFilters.value.visitor_search}`);
  
  return parts.join(' | ');
});

const fetchStats = async () => {
  try {
    isLoading.value = true;
    const data = await VisitService.getDashboardStats();
    stats.value = data;
  } catch (error) {
    logger.error('Error al cargar estadísticas', error);
  } finally {
    isLoading.value = false;
  }
};

const searchPreview = async () => {
  if (!isFormValid.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Campos Requeridos',
      text: 'Por favor selecciona el rango de fechas para generar el reporte',
      confirmButtonColor: '#3085d6',
    });
    return;
  }

  try {
    isSearching.value = true;
    currentPage.value = 1; // Reset página al buscar
    
    // Preparar filtros - convertir mission_case según la opción seleccionada
    const filters = {
      ...reportFilters.value
    };
    
    // Convertir el valor del select a booleano o undefined
    if (filters.mission_case === 'only') {
      filters.mission_case = true; // Solo casos misionales
    } else if (filters.mission_case === 'exclude') {
      filters.mission_case = false; // Solo casos NO misionales
    } else {
      delete filters.mission_case; // Ambos (no filtrar)
    }
    
    const response = await VisitService.advancedSearch(filters);
    previewData.value = response.visits || response || [];
    hasSearched.value = true;
    
    // Verificar si se aplicó un límite
    if (response.limited) {
      Swal.fire({
        icon: 'warning',
        title: 'Resultados Limitados',
        html: `Se encontraron <strong>${response.total_count}</strong> registros, pero solo se muestran los primeros <strong>${response.limit_applied}</strong>.<br><br>Por favor, refina tus filtros para obtener resultados más específicos.`,
        confirmButtonColor: '#f59e0b',
      });
    } else if (previewData.value.length === 0) {
      Swal.fire({
        icon: 'info',
        title: 'Sin Resultados',
        text: 'No se encontraron visitas con los filtros seleccionados',
        confirmButtonColor: '#3085d6',
      });
    }
    // Eliminada la notificación de éxito que estorbaba
  } catch (error) {
    logger.error('Error al buscar visitas', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudo realizar la búsqueda',
      confirmButtonColor: '#ef4444',
    });
  } finally {
    isSearching.value = false;
  }
};

const exportToExcel = async () => {
  if (!hasSearched.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Búsqueda Requerida',
      text: 'Por favor realiza una búsqueda antes de exportar',
      confirmButtonColor: '#3085d6',
    });
    return;
  }

  if (previewData.value.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Sin Datos',
      text: 'No hay datos para exportar',
      confirmButtonColor: '#3085d6',
    });
    return;
  }

  try {
    isExporting.value = true;
    
    // Preparar filtros - convertir mission_case según la opción seleccionada
    const filters = { ...reportFilters.value };
    
    if (filters.mission_case === 'only') {
      filters.mission_case = true;
    } else if (filters.mission_case === 'exclude') {
      filters.mission_case = false;
    } else {
      delete filters.mission_case;
    }
    
    const blob = await VisitService.exportToExcel(filters);
    
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    
    const fileName = `visitas_${reportFilters.value.start_date}_${reportFilters.value.end_date}.xlsx`;
    link.setAttribute('download', fileName);
    
    document.body.appendChild(link);
    link.click();
    
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  
    Swal.fire({
      icon: 'success',
      title: 'Exportado',
      text: 'El archivo Excel ha sido descargado exitosamente',
      confirmButtonColor: '#10b981',
    });
  } catch (error) {
    logger.error('Error al exportar a Excel', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudo exportar el archivo',
      confirmButtonColor: '#ef4444',
    });
  } finally {
    isExporting.value = false;
  }
};

const exportToPDF = async () => {
  if (!hasSearched.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Búsqueda Requerida',
      text: 'Por favor realiza una búsqueda antes de exportar',
      confirmButtonColor: '#3085d6',
    });
    return;
  }

  if (previewData.value.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Sin Datos',
      text: 'No hay datos para exportar',
      confirmButtonColor: '#3085d6',
    });
    return;
  }

  try {
    isExporting.value = true;
    
    // Preparar filtros - convertir mission_case según la opción seleccionada
    const filters = { ...reportFilters.value };
    
    if (filters.mission_case === 'only') {
      filters.mission_case = true;
    } else if (filters.mission_case === 'exclude') {
      filters.mission_case = false;
    } else {
      delete filters.mission_case;
    }
    
    const blob = await VisitService.exportToPDF(filters);
    
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    
    const fileName = `visitas_${reportFilters.value.start_date}_${reportFilters.value.end_date}.pdf`;
    link.setAttribute('download', fileName);
    
    document.body.appendChild(link);
    link.click();
    
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  
    Swal.fire({
      icon: 'success',
      title: 'Exportado',
      text: 'El archivo PDF ha sido descargado exitosamente',
      confirmButtonColor: '#10b981',
    });
  } catch (error) {
    logger.error('Error al exportar a PDF', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudo exportar el archivo PDF',
      confirmButtonColor: '#ef4444',
    });
  } finally {
    isExporting.value = false;
  }
};

const clearFilters = () => {
  reportFilters.value = {
    start_date: '',
    end_date: '',
    person_visited: '',
    visitor_search: '',
    department: '',
    assigned_carnet: '',
    mission_case: 'all'
  };
  previewData.value = [];
  hasSearched.value = false;
};

// Funciones auxiliares
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

const formatDateOnly = (dateString) => {
  if (!dateString) return '—';
  const date = new Date(dateString);
  return date.toLocaleDateString("es-DO");
};

// Alias para compatibilidad con template existente
const getVisitorName = getVisitorFullName;

const getPhone = (visit) => visit.visitors?.[0]?.phone ?? '—';

const getEmail = (visit) => visit.visitors?.[0]?.email ?? '—';

const getRegisteredBy = (visit) => visit.user?.name ?? '—';

const getClosedBy = (visit) => visit.closed_by_user?.name ?? '—';

const getStatusBadge = (statusId) => {
  return statusId === 1 
    ? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Abierto</span>'
    : '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Cerrado</span>';
};

const truncateText = (text, maxLength = 50) => {
  if (!text) return '—';
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};

// Propiedad computada para el rango de fechas basado en el filtro
const dateRange = computed(() => {
  const filter = timeFilters.find(f => f.value === selectedFilter.value);
  const end = new Date();
  const start = new Date();
  start.setDate(start.getDate() - filter.days);
  
  return {
    from: start.toISOString().split('T')[0],
    to: end.toISOString().split('T')[0]
  };
});

// Propiedad computada para la fecha formateada de última actualización
const formattedLastUpdated = computed(() => {
  return lastUpdated.value.toLocaleString('es-DO', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  });
});

// Optimizado: Cargar TODAS las estadísticas en paralelo
async function loadAllData() {
  isLoadingStats.value = true;
  isLoading.value = true;
  
  try {
    // Seleccionar el endpoint correcto según el rol
    let dashboardStatsPromise;
    if (userRole.value === 'aux_ugc') {
      // aux_ugc solo ve estadísticas misionales
      dashboardStatsPromise = VisitService.getMissionStatsOnly();
    } else {
      // Admin, Asist_adm y Guardia ven TODAS las estadísticas
      dashboardStatsPromise = VisitService.getDashboardStats();
    }

    // Una sola llamada a Promise.all() para máxima eficiencia
    const [dashboardStats, kpisData, trend, dept, dur, hourly, weekday, weekly] = await Promise.all([
      dashboardStatsPromise,
      StatsService.getKPIs(dateRange.value.from, dateRange.value.to),
      StatsService.getDailyTrend(dateRange.value.from, dateRange.value.to),
      StatsService.getByDepartment(dateRange.value.from, dateRange.value.to),
      StatsService.getAverageDuration(dateRange.value.from, dateRange.value.to),
      StatsService.getHourlyPeak(dateRange.value.from, dateRange.value.to),
      StatsService.getWeekdayAverage(dateRange.value.from, dateRange.value.to),
      StatsService.getWeeklyCompare(dateRange.value.to)
    ]);

    // Actualizar todo el estado de una vez
    stats.value = dashboardStats;
    kpis.value = kpisData;
    trendData.value = trend;
    departmentData.value = dept;
    durationData.value = dur;
    hourlyData.value = hourly;
    weekdayData.value = weekday;
    weeklyCompareData.value = weekly;
    
    // Actualizar marca de tiempo
    lastUpdated.value = new Date();
  } catch (error) {
    logger.error('Error al cargar datos', error);
    Swal.fire({
      icon: 'error',
      title: 'Error al Cargar Datos',
      text: 'No se pudieron cargar las estadísticas. Verifica tu conexión e inténtalo de nuevo.',
      confirmButtonColor: '#ef4444',
    });
  } finally {
    isLoadingStats.value = false;
    isLoading.value = false;
  }
}

// Cargar solo los datos de gráficos cuando cambia el filtro
async function loadChartsOnly() {
  isLoadingStats.value = true;
  try {
    const [kpisData, trend, dept, dur, hourly, weekday, weekly] = await Promise.all([
      StatsService.getKPIs(dateRange.value.from, dateRange.value.to),
      StatsService.getDailyTrend(dateRange.value.from, dateRange.value.to),
      StatsService.getByDepartment(dateRange.value.from, dateRange.value.to),
      StatsService.getAverageDuration(dateRange.value.from, dateRange.value.to),
      StatsService.getHourlyPeak(dateRange.value.from, dateRange.value.to),
      StatsService.getWeekdayAverage(dateRange.value.from, dateRange.value.to),
      StatsService.getWeeklyCompare(dateRange.value.to)
    ]);

    kpis.value = kpisData;
    trendData.value = trend;
    departmentData.value = dept;
    durationData.value = dur;
    hourlyData.value = hourly;
    weekdayData.value = weekday;
    weeklyCompareData.value = weekly;
    
    // Actualizar marca de tiempo
    lastUpdated.value = new Date();
  } catch (error) {
    logger.error('Error al cargar gráficos', error);
    Swal.fire({
      icon: 'error',
      title: 'Error al Cargar Gráficos',
      text: 'No se pudieron actualizar los gráficos.',
      confirmButtonColor: '#ef4444',
    });
  } finally {
    isLoadingStats.value = false;
  }
}

// Observar cambios en el filtro - solo recargar gráficos
watch(selectedFilter, () => {
  loadChartsOnly();
});

onMounted(() => {
  loadAllData();
});
</script>

<template>
  <AppLayout :stats="{ totalVisitors: stats.total_visitors_this_week, todayVisitors: stats.today_visitors, activeVisits: stats.active_visits }">
    <div class="max-w-7xl mx-auto space-y-8">
      <!-- Header -->
      <div class="text-left">
        <h1 class="text-2xl font-bold text-gray-900">
          Estadísticas y Reportes
        </h1>
        <p class="text-sm text-gray-600 mt-1">
          Panel de control con estadísticas en tiempo real y generación de reportes
        </p>
      </div>

      <!-- Unified Statistics Grid - Both Overview and KPI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Row 1: Today stats -->
        <StatsCard 
          title="Visitas Hoy"
          :value="stats.today_visitors"
          icon="mdi:calendar-today"
          color="green"
          :loading="isLoading"
        />
        <StatsCard 
          title="Visitas Esta Semana"
          :value="stats.total_visitors_this_week"
          icon="mdi:calendar-week"
          color="blue"
          :loading="isLoading"
        />
        <StatsCard 
          title="Visitas Activas"
          :value="stats.active_visits"
          icon="mdi:door-open"
          color="purple"
          :loading="isLoading"
        />
        <StatsCard 
          title="Cerradas Hoy"
          :value="stats.closed_visitors"
          icon="mdi:check-circle"
          color="orange"
          :loading="isLoading"
        />

        <!-- Row 2: Additional stats -->
        <StatsCard 
          title="Casos Misionales"
          :value="stats.mission_cases_week"
          icon="mdi:briefcase"
          color="pink"
          :loading="isLoading"
        />
        <StatsCard 
          title="Total Histórico"
          :value="stats.total_visits_all_time"
          icon="mdi:chart-line"
          color="blue"
          :loading="isLoading"
        />
        <StatsCard 
          title="Promedio Diario"
          :value="kpis.dailyAverage"
          icon="mdi:chart-bar"
          color="purple"
          :loading="isLoading"
        />
        <StatsCard 
          title="Duración Promedio"
          :value="`${kpis.avgDuration} min`"
          icon="mdi:clock-outline"
          color="orange"
          :loading="isLoading"
        />
      </div>

      <!-- Time Range Filters for Charts -->
      <div class="flex items-center gap-3 bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <Icon icon="mdi:filter" class="w-5 h-5 text-gray-500" />
        <span class="text-sm text-gray-600 font-medium">Filtro de gráficos:</span>
        <div class="flex gap-2 flex-wrap">
          <button
            v-for="filter in timeFilters"
            :key="filter.value"
            @click="selectedFilter = filter.value"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition-all',
              selectedFilter === filter.value
                ? 'bg-demo-blue-600 text-white shadow-md'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            ]"
          >
            {{ filter.label }}
          </button>
        </div>
      </div>

      <!-- Charts Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Trend Chart -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Icon icon="mdi:chart-line-variant" class="w-5 h-5 text-demo-blue-600" />
            Tendencia de Visitas
          </h3>
          <VisitsTrendChart :data="trendData" :loading="isLoadingStats" />
        </div>

        <!-- Department Chart -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Icon icon="mdi:office-building" class="w-5 h-5 text-demo-blue-600" />
            Visitas por Departamento
          </h3>
          <DepartmentBarsChart :data="departmentData" :loading="isLoadingStats" />
        </div>

        <!-- Duration Gauge -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Icon icon="mdi:timer-outline" class="w-5 h-5 text-demo-blue-600" />
            Duración Promedio de Visitas
          </h3>
          <VisitsDurationGauge :duration="durationData" :loading="isLoadingStats" />
        </div>

        <!-- Hourly Peak Chart -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Icon icon="mdi:clock-time-four-outline" class="w-5 h-5 text-demo-blue-600" />
            Picos por Hora
          </h3>
          <HourlyPeakChart :data="hourlyData" :loading="isLoadingStats" />
        </div>

        <!-- Weekday Average Chart -->
        <div v-if="selectedFilter === 'week'" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Icon icon="mdi:calendar-week" class="w-5 h-5 text-demo-blue-600" />
            Promedio de Visitas por Día
          </h3>
          <WeekdayAverageChart :data="weekdayData" :loading="isLoadingStats" />
        </div>

        <!-- Weekly Compare Chart -->
        <div v-if="selectedFilter === 'week'" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Icon icon="mdi:calendar-compare" class="w-5 h-5 text-demo-blue-600" />
            Comparación Semanal
          </h3>
          <WeeklyCompareChart :data="weeklyCompareData" :loading="isLoadingStats" />
        </div>
      </div>

      <!-- Reports Section -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 px-8 py-6 border-b border-indigo-100">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center">
              <Icon icon="mdi:file-chart" class="w-6 h-6 text-indigo-600" />
            </div>
            <div>
              <h2 class="text-xl font-semibold text-gray-900">Generador de Reportes</h2>
              <p class="text-sm text-gray-600">Configure los filtros y genere reportes personalizados en Excel o PDF</p>
            </div>
          </div>
        </div>

        <div class="p-8 space-y-8">
          <!-- Filter Form -->
          <div class="space-y-6">
            <!-- Date Range (Required) -->
            <div class=" p-4">
              <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                <Icon icon="mdi:calendar-range" class="w-4 h-4" />
                Rango de Fechas 
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Fecha Inicio</label>
                  <input 
                    v-model="reportFilters.start_date"
                    type="date" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all text-sm"
                    required
                  />
                </div>
                <div class="space-y-2">
                  <label class="text-sm font-medium text-gray-700">Fecha Fin</label>
                  <input 
                    v-model="reportFilters.end_date"
                    type="date" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all text-sm"
                    required
                  />
                </div>
              </div>
            </div>

            <!-- Optional Filters - Collapsible -->
            <div class="bg-gray-50 border border-gray-200 rounded-xl overflow-hidden">
              <button
                @click="showOptionalFilters = !showOptionalFilters"
                class="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-100 transition-colors"
              >
                <div class="flex items-center gap-2">
                  <Icon icon="mdi:filter" class="w-4 h-4" />
                  <h3 class="text-sm font-semibold text-gray-700">Filtros Opcionales</h3>
                </div>
                <Icon 
                  icon="mdi:chevron-down"
                  class="w-5 h-5 text-gray-400 transition-transform"
                  :class="{ 'rotate-180': showOptionalFilters }"
                />
              </button>
              
              <div v-if="showOptionalFilters" class="px-4 py-4 border-t border-gray-200 space-y-4">
                <!-- Row 1: Persona Visitada & Visitante -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700">Persona Visitada</label>
                    <div class="relative">
                      <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <Icon icon="mdi:account" class="w-5 h-5 text-gray-400" />
                      </div>
                      <input 
                        v-model="reportFilters.person_visited"
                        type="text" 
                        placeholder="Nombre de la persona visitada"
                        class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
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
                        v-model="reportFilters.visitor_search"
                        type="text" 
                        placeholder="Nombre o número de documento"
                        class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
                      />
                    </div>
                  </div>
                </div>

                <!-- Row 2: Departamento & Carnet -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700">Departamento</label>
                    <div class="relative">
                      <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <Icon icon="mdi:office-building" class="w-5 h-5 text-gray-400" />
                      </div>
                      <input 
                        v-model="reportFilters.department"
                        type="text" 
                        placeholder="Nombre del departamento"
                        class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
                      />
                    </div>
                  </div>

                  <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-700">Número de Carnet</label>
                    <div class="relative">
                      <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <Icon icon="mdi:badge-account" class="w-5 h-5 text-gray-400" />
                      </div>
                      <input 
                        v-model="reportFilters.assigned_carnet"
                        type="text" 
                        placeholder="número de carnet"
                        class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
                      />
                    </div>
                  </div>
                </div>

                <!-- Mission Case Filter -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                  <label for="missionCaseFilter" class="block text-sm font-semibold text-gray-900 mb-2">
                    Tipo de Visita
                  </label>
                  <div class="relative">
                    <Icon icon="mdi:briefcase" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-blue-600" />
                    <select 
                      id="missionCaseFilter"
                      v-model="reportFilters.mission_case"
                      class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm appearance-none bg-white cursor-pointer"
                    >
                      <option value="all">Todos (Misionales y Regulares)</option>
                      <option value="only">Solo Casos Misionales</option>
                      <option value="exclude">Solo Casos Regulares</option>
                    </select>
                    <Icon icon="mdi:chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" />
                  </div>
                  <p class="text-xs text-gray-600 mt-2">
                    Filtra las visitas según su clasificación como caso misional
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex flex-wrap gap-4">
            <button 
              @click="searchPreview"
              :disabled="!isFormValid || isSearching"
              class="flex-1 md:flex-none px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:from-gray-300 disabled:to-gray-400 text-white font-semibold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl disabled:shadow-none"
            >
              <Icon v-if="isSearching" icon="mdi:loading" class="w-5 h-5 animate-spin" />
              <Icon v-else icon="mdi:magnify" class="w-5 h-5" />
              {{ isSearching ? 'Generando...' : 'Generar Reporte' }}
            </button>

            <button 
              @click="exportToExcel"
              :disabled="!hasSearched || isExporting || previewData.length === 0"
              class="flex-1 md:flex-none px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 disabled:from-gray-300 disabled:to-gray-400 text-white font-semibold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl disabled:shadow-none"
            >
              <Icon v-if="isExporting" icon="mdi:loading" class="w-5 h-5 animate-spin" />
              <Icon v-else icon="mdi:file-excel" class="w-5 h-5" />
              {{ isExporting ? 'Exportando...' : 'Exportar Excel' }}
            </button>

            <button 
              @click="exportToPDF"
              :disabled="!hasSearched || isExporting || previewData.length === 0"
              class="flex-1 md:flex-none px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 disabled:from-gray-300 disabled:to-gray-400 text-white font-semibold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl disabled:shadow-none"
            >
              <Icon v-if="isExporting" icon="mdi:loading" class="w-5 h-5 animate-spin" />
              <Icon v-else icon="mdi:file-pdf-box" class="w-5 h-5" />
              {{ isExporting ? 'Exportando...' : 'Exportar PDF' }}
            </button>

            <button 
              @click="clearFilters"
              class="px-6 py-3 border border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-all duration-200 flex items-center gap-2"
            >
              <Icon icon="mdi:refresh" class="w-5 h-5" />
              Limpiar
            </button>
          </div>

          <!-- Applied Filters Banner -->
          <div v-if="hasSearched" class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <Icon icon="mdi:filter-check" class="w-5 h-5 text-blue-600" />
              </div>
              <div class="flex-1">
                <h4 class="text-sm font-semibold text-blue-900 mb-1">Filtros Aplicados</h4>
                <p class="text-sm text-blue-700 mb-2">{{ appliedFiltersText }}</p>
                <div class="flex items-center gap-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <Icon icon="mdi:chart-bar" class="w-3 h-3 mr-1" />
                    {{ previewData.length }} resultados
                  </span>
                  <span v-if="previewData.length > 0" class="text-xs text-blue-600">
                    Datos listos para exportar
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Data Preview Section -->
      <div v-if="hasSearched" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-slate-50 to-gray-50 px-8 py-6 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center">
                <Icon icon="mdi:table" class="w-5 h-5 text-slate-600" />
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Previsualización de Datos</h3>
                <p class="text-sm text-gray-600">{{ previewData.length }} registros encontrados</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                <Icon icon="mdi:check-circle" class="w-3 h-3 mr-1" />
                Listo para exportar
              </span>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="previewData.length === 0" class="p-16 text-center">
          <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
            <Icon icon="mdi:database-search" class="w-12 h-12 text-gray-400" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No se encontraron resultados</h3>
          <p class="text-gray-500 max-w-md mx-auto">
            No hay visitas que coincidan con los filtros aplicados. Pruebe ajustando el rango de fechas o los criterios de búsqueda.
          </p>
        </div>

        <!-- Data Table -->
        <div v-else class="overflow-hidden">
          <div class="overflow-x-auto" style="max-height: 600px;">
            <table class="w-full" style="min-width: 1400px;">
              <thead class="bg-slate-100 sticky top-0 z-10">
                <tr class="border-b border-slate-200">
                  <th class="px-4 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider" style="width: 60px;">ID</th>
                  <th class="px-4 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider" style="width: 160px;">Visitante</th>
                  <th class="px-4 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider" style="width: 140px;">Documento</th>
                  <th class="px-4 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider" style="width: 120px;">Teléfono</th>
                  <th class="px-4 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider" style="width: 160px;">Email</th>
                  <th class="px-4 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider" style="width: 150px;">Persona Visitada</th>
                  <th class="px-4 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider" style="width: 100px;">Tipo</th>
                  <th class="px-4 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider" style="width: 140px;">Entrada</th>
                  <th class="px-4 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider" style="width: 140px;">Salida</th>
                  <th class="px-4 py-4 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider" style="width: 200px;">Motivo</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="visit in paginatedData" :key="visit.id" class="hover:bg-blue-50 transition-colors">
                  <td class="px-4 py-4 whitespace-nowrap">
                    <span class="text-sm font-medium text-gray-900">{{ visit.id }}</span>
                  </td>
                  <td class="px-4 py-4">
                    <div class="text-sm font-medium text-gray-900">{{ getVisitorName(visit) }}</div>
                  </td>
                  <td class="px-4 py-4">
                    <div class="text-sm font-medium text-gray-500">{{ getFormattedDocument(visit) }}</div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap">
                    <span class="text-sm text-gray-700">{{ getPhone(visit) }}</span>
                  </td>
                  <td class="px-4 py-4">
                    <span class="text-sm text-gray-700">{{ truncateText(getEmail(visit), 25) }}</span>
                  </td>
                  <td class="px-4 py-4">
                    <span class="text-sm text-gray-900">{{ visit.namePersonToVisit || '—' }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap">
                    <span v-if="visit.mission_case" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                      <Icon icon="mdi:briefcase" class="w-3 h-3 mr-1" />
                      Misional
                    </span>
                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                      Regular
                    </span>
                  </td>
                  <td class="px-4 py-4">
                    <div class="text-sm text-gray-900">{{ visit.created_at?.formatted || visit.created_at_raw || '—' }}</div>
                  </td>
                  <td class="px-4 py-4">
                    <div class="text-sm text-gray-900">
                      {{ visit.end_at?.formatted || (visit.end_at_raw ? visit.end_at_raw : '—') }}
                    </div>
                  </td>
                  <td class="px-4 py-4">
                    <div class="text-sm text-gray-600 leading-relaxed">
                      {{ truncateText(visit.reason, 60) }}
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="totalPages > 1" class="bg-white border-t border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2 text-sm text-gray-700">
                <Icon icon="mdi:information-outline" class="w-4 h-4" />
                Mostrando {{ (currentPage - 1) * itemsPerPage + 1 }} a {{ Math.min(currentPage * itemsPerPage, previewData.length) }} de {{ previewData.length }} resultados
              </div>
              
              <nav class="flex items-center gap-1">
                <button 
                  @click="changePage(currentPage - 1)"
                  :disabled="currentPage === 1"
                  class="p-2 text-gray-400 hover:text-gray-600 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg hover:bg-gray-100 transition-colors"
                >
                  <Icon icon="mdi:chevron-left" class="w-5 h-5" />
                </button>
                
                <button 
                  v-for="page in Math.min(totalPages, 5)"
                  :key="page"
                  @click="changePage(page)"
                  :class="[
                    'px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                    page === currentPage 
                      ? 'bg-blue-600 text-white' 
                      : 'text-gray-700 hover:bg-gray-100'
                  ]"
                >
                  {{ page }}
                </button>
                
                <span v-if="totalPages > 5" class="px-2 text-gray-500">...</span>
                
                <button 
                  @click="changePage(currentPage + 1)"
                  :disabled="currentPage === totalPages"
                  class="p-2 text-gray-400 hover:text-gray-600 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg hover:bg-gray-100 transition-colors"
                >
                  <Icon icon="mdi:chevron-right" class="w-5 h-5" />
                </button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>