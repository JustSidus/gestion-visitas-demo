import { ref, computed } from 'vue';
import VisitService from '@/services/VisitService';
import logger from '@/utils/logger';

/**
 * Composable reutilizable para búsqueda avanzada de visitas cerradas
 * Consolida la lógica común entre VisitHistory y VisitsTable
 * 
 * @returns {Object} Estado reactivo y funciones de búsqueda
 */
export function useHistorialSearch() {
  // ============= ESTADO REACTIVO =============
  const isLoadingVisits = ref(false);
  const visits = ref([]);
  const search = ref('');
  const showAdvancedFilters = ref(false);
  const currentPage = ref(1);
  const lastUpdateTime = ref(new Date());

  const itemsPerPage = 15;

  // Filtros avanzados
  const filters = ref({
    start_date: '',
    end_date: '',
    person_visited: '',
    department: '',
    visitor_search: '',
    mission_case: 'all' // 'all', 'only', 'exclude'
  });

  // ============= COMPUTED =============
  /**
   * Calcula los datos paginados
   */
  const paginatedVisits = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return visits.value.slice(start, end);
  });

  /**
   * Calcula el número total de páginas
   */
  const totalPages = computed(() => Math.ceil(visits.value.length / itemsPerPage));

  /**
   * Verifica si hay filtros avanzados activos
   */
  const hasAdvancedFilters = computed(() => {
    return (
      filters.value.start_date ||
      filters.value.end_date ||
      filters.value.person_visited ||
      filters.value.visitor_search ||
      filters.value.mission_case !== 'all'
    );
  });

  // ============= FUNCIONES DE BÚSQUEDA =============
  /**
   * Realiza la búsqueda de visitas cerradas
   * - Si hay filtros avanzados: Usa búsqueda avanzada
   * - Si no: Usa búsqueda simple de hoy
   * 
   * @param {Boolean} silent - Si es true, no muestra indicador de carga
   */
  const fetchVisits = async (silent = false) => {
    // Si no es silencioso, mostrar indicador de carga
    if (!silent) {
      isLoadingVisits.value = true;
      currentPage.value = 1; // Reset página al buscar manualmente
    }

    try {
      let data;
      const query = search.value?.trim();

      if (hasAdvancedFilters.value) {
        // BÚSQUEDA AVANZADA: Con filtros específicos (solo cerradas)
        const filterParams = {
          ...filters.value,
          visitor_search: query || filters.value.visitor_search,
          status: 2 // Forzar status cerrado
        };
        
        // Convertir mission_case según la opción seleccionada
        if (filterParams.mission_case === 'only') {
          filterParams.mission_case = true;
        } else if (filterParams.mission_case === 'exclude') {
          filterParams.mission_case = false;
        } else {
          delete filterParams.mission_case; // 'all' = no filtrar
        }
        
        const response = await VisitService.advancedSearch(filterParams);
        data = response || [];
      } else {
        // BÚSQUEDA SIMPLE: Cerradas de hoy por defecto
        data = await VisitService.getClosedTodayVisits(query);
      }

      // Actualizar visitas
      visits.value = data;
      lastUpdateTime.value = new Date();

      logger.log(
        `Historial actualizado: ${visits.value.length} resultados - ${lastUpdateTime.value.toLocaleTimeString()}`
      );
    } catch (error) {
      logger.error('Error al cargar historial de visitas', error);
      visits.value = [];
    } finally {
      if (!silent) {
        isLoadingVisits.value = false;
      }
    }
  };

  /**
   * Limpia los filtros y recarga los datos
   */
  const clearFilters = () => {
    filters.value = {
      start_date: '',
      end_date: '',
      person_visited: '',
      department: '',
      visitor_search: '',
      mission_case: 'all'
    };
    search.value = '';
    fetchVisits();
  };

  /**
   * Alterna la visibilidad de filtros avanzados
   */
  const toggleAdvancedFilters = () => {
    showAdvancedFilters.value = !showAdvancedFilters.value;
  };

  /**
   * Navega a una página específica
   * 
   * @param {Number} page - Número de página
   */
  const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page;
      // Scroll suave hacia arriba
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  /**
   * Exporta datos a Excel
   */
  const exportToExcel = async () => {
    try {
      const filterParams = {
        ...filters.value,
        visitor_search: search.value || filters.value.visitor_search
      };

      // Convertir mission_case según la opción seleccionada
      if (filterParams.mission_case === 'only') {
        filterParams.mission_case = true;
      } else if (filterParams.mission_case === 'exclude') {
        filterParams.mission_case = false;
      } else {
        delete filterParams.mission_case;
      }

      const blob = await VisitService.exportToExcel(filterParams);
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute(
        'download',
        `visitas_${new Date().toISOString().split('T')[0]}.xlsx`
      );
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      return { success: true };
    } catch (error) {
      logger.error('Error al exportar a Excel', error);
      return { success: false, error };
    }
  };

  /**
   * Exporta datos a PDF
   */
  const exportToPDF = async () => {
    try {
      const filterParams = {
        ...filters.value,
        visitor_search: search.value || filters.value.visitor_search
      };

      // Convertir mission_case según la opción seleccionada
      if (filterParams.mission_case === 'only') {
        filterParams.mission_case = true;
      } else if (filterParams.mission_case === 'exclude') {
        filterParams.mission_case = false;
      } else {
        delete filterParams.mission_case;
      }

      const blob = await VisitService.exportToPDF(filterParams);
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute(
        'download',
        `visitas_${new Date().toISOString().split('T')[0]}.pdf`
      );
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      return { success: true };
    } catch (error) {
      logger.error('Error al exportar a PDF', error);
      return { success: false, error };
    }
  };

  // ============= RETORNO PÚBLICO =============
  return {
    // Estado
    isLoadingVisits,
    visits,
    search,
    showAdvancedFilters,
    currentPage,
    filters,
    lastUpdateTime,

    // Computed
    paginatedVisits,
    totalPages,
    hasAdvancedFilters,

    // Funciones
    fetchVisits,
    clearFilters,
    toggleAdvancedFilters,
    changePage,
    exportToExcel,
    exportToPDF,

    // Constantes
    itemsPerPage
  };
}
