import api from "../api/api";

/**
 * Servicio para gestionar alertas
 */
const AlertService = {
  /**
   * Registrar una nueva alerta
   * @param {Object} alertData - Datos de la alerta
   * @returns {Promise<Object>} - Respuesta del servidor con case_id
   */
  async registerAlert(alertData) {
    try {
      const response = await api.post('/alertas', alertData);
      return response.data;
    } catch (error) {
      console.error('Error al registrar alerta:', error);
      throw error;
    }
  },

  /**
   * Obtener detalles de una alerta por case_id
   * @param {number} caseId - ID del caso
   * @returns {Promise<Object>} - Detalles completos de la alerta
   */
  async getAlertDetails(caseId) {
    try {
      const response = await api.get(`/alertas/${caseId}`);
      return response.data;
    } catch (error) {
      console.error('Error al obtener detalles de alerta:', error);
      throw error;
    }
  },

  /**
   * Verificar si una visita ya tiene alerta registrada
   * @param {number} visitId - ID de la visita
   * @param {number} visitorId - ID del visitante
   * @returns {Promise<Object>} - { has_alert: boolean, case_id: number|null }
   */
  async checkAlertStatus(visitId, visitorId) {
    try {
      const response = await api.post('/alertas/check-status', {
        visit_id: visitId,
        visitor_id: visitorId,
      });
      return response.data;
    } catch (error) {
      console.error('Error al verificar estado de alerta:', error);
      throw error;
    }
  },

  /**
   * Obtener alerta por visit_id y visitor_id
   * @param {number} visitId - ID de la visita
   * @param {number} visitorId - ID del visitante
   * @returns {Promise<Object>} - Detalles completos de la alerta
   */
  async getAlertByVisit(visitId, visitorId) {
    try {
      const response = await api.post('/alertas/by-visit', {
        visit_id: visitId,
        visitor_id: visitorId,
      });
      return response.data;
    } catch (error) {
      console.error('Error al obtener alerta por visita:', error);
      throw error;
    }
  },

  // ============================================================================
  // CATÁLOGOS Y DATOS MAESTROS (desde base externa de alertas)
  // ============================================================================

  /**
   * Obtener todos los tipos de origen de caso
   * @returns {Promise<Array>} - Lista de tipos de origen
   */
  async getOriginTypes() {
    try {
      const response = await api.get('/catalogos/tipos-origen');
      return response.data;
    } catch (error) {
      console.error('Error al obtener tipos de origen:', error);
      throw error;
    }
  },

  /**
   * Obtener casos de origen filtrados por tipo
   * @param {number} typeId - ID del tipo de origen
   * @returns {Promise<Array>} - Lista de casos de origen
   */
  async getOriginCasesByType(typeId) {
    try {
      const response = await api.get(`/catalogos/casos-origen/${typeId}`);
      return response.data;
    } catch (error) {
      console.error('Error al obtener casos de origen:', error);
      throw error;
    }
  },

  /**
   * Obtener todos los tipos de alerta
   * @returns {Promise<Array>} - Lista de tipos de alerta
   */
  async getAlertTypes() {
    try {
      const response = await api.get('/catalogos/tipos-alerta');
      return response.data;
    } catch (error) {
      console.error('Error al obtener tipos de alerta:', error);
      throw error;
    }
  },

  /**
   * Obtener todas las provincias
   * @returns {Promise<Array>} - Lista de provincias
   */
  async getProvinces() {
    try {
      const response = await api.get('/catalogos/provincias');
      return response.data;
    } catch (error) {
      console.error('Error al obtener provincias:', error);
      throw error;
    }
  },

  /**
   * Obtener municipios por provincia
   * @param {number} provinceId - ID de la provincia
   * @returns {Promise<Array>} - Lista de municipios
   */
  async getMunicipalitiesByProvince(provinceId) {
    try {
      const response = await api.get(`/catalogos/municipios/${provinceId}`);
      return response.data;
    } catch (error) {
      console.error('Error al obtener municipios:', error);
      throw error;
    }
  },

  /**
   * Obtener todos los municipios (sin filtrar)
   * @returns {Promise<Array>} - Lista completa de municipios
   */
  async getAllMunicipalities() {
    try {
      const response = await api.get('/catalogos/municipios');
      return response.data;
    } catch (error) {
      console.error('Error al obtener todos los municipios:', error);
      throw error;
    }
  },

  /**
   * Obtener instituciones que dan alertas
   * @returns {Promise<Array>} - Lista de instituciones
   */
  async getInstitutionsWhoGiveAlerts() {
    try {
      const response = await api.get('/catalogos/instituciones-alertas');
      return response.data;
    } catch (error) {
      console.error('Error al obtener instituciones:', error);
      throw error;
    }
  },

  /**
   * Obtener redes sociales o canales de comunicación
   * @returns {Promise<Array>} - Lista de redes sociales
   */
  async getSocialMediaOrNewChannels() {
    try {
      const response = await api.get('/catalogos/redes-sociales');
      return response.data;
    } catch (error) {
      console.error('Error al obtener redes sociales:', error);
      throw error;
    }
  },

  /**
  * Obtener protocolos institucionales
   * @returns {Promise<Array>} - Lista de protocolos
   */
  async getInstitutionalProtocols() {
    try {
      const response = await api.get('/catalogos/protocolos-institucion');
      return response.data;
    } catch (error) {
      console.error('Error al obtener protocolos institucionales:', error);
      throw error;
    }
  },

  /**
   * Obtener posiciones de empleados
   * @returns {Promise<Array>} - Lista de posiciones
   */
  async getEmployeePositions() {
    try {
      const response = await api.get('/catalogos/posiciones-empleados');
      return response.data;
    } catch (error) {
      console.error('Error al obtener posiciones de empleados:', error);
      throw error;
    }
  },

  /**
   * Obtener géneros
   * @returns {Promise<Array>} - Lista de géneros (Masculino, Femenino)
   */
  async getGenders() {
    try {
      const response = await api.get('/catalogos/generos');
      return response.data;
    } catch (error) {
      console.error('Error al obtener géneros:', error);
      throw error;
    }
  },

  /**
   * Buscar NNAs por nombre y apellido
   * @param {string} name - Nombre del NNA
   * @param {string} surname - Apellido del NNA
   * @returns {Promise<Array>} - Lista de NNAs coincidentes
   */
  async searchNNA(name, surname) {
    try {
      const response = await api.get('/catalogos/buscar-nna', {
        params: {
          name,
          surname,
        },
      });
      return response.data;
    } catch (error) {
      console.error('Error al buscar NNA:', error);
      throw error;
    }
  },

  /**
   * Cargar todos los datos maestros en paralelo (optimización)
   * @returns {Promise<Object>} - Objeto con todas las categorías cargadas
   */
  async loadAllMasterData() {
    try {
      const [
        originTypes,
        alertTypes,
        provinces,
        allMunicipalities,
        institutions,
        socialMedia,
        institutionalProtocols,
        employeePositions,
        genders,
      ] = await Promise.all([
        this.getOriginTypes(),
        this.getAlertTypes(),
        this.getProvinces(),
        this.getAllMunicipalities(),
        this.getInstitutionsWhoGiveAlerts(),
        this.getSocialMediaOrNewChannels(),
        this.getInstitutionalProtocols(),
        this.getEmployeePositions(),
        this.getGenders(),
      ]);

      return {
        originTypes,
        alertTypes,
        provinces,
        allMunicipalities,
        institutions,
        socialMedia,
        institutionalProtocols,
        employeePositions,
        genders,
      };
    } catch (error) {
      console.error('Error al cargar datos maestros:', error);
      throw error;
    }
  },

  /**
   * Cargar todos los datos maestros consolidados (OPTIMIZADO - 1 petición)
   * @returns {Promise<Object>} - Objeto con todas las categorías cargadas
   */
  async loadAllMasterDataConsolidated() {
    try {
      const response = await api.get('/catalogos/master-data');
      
      return {
        originTypes: response.data.originTypes || [],
        alertTypes: response.data.alertTypes || [],
        provinces: response.data.provinces || [],
        allMunicipalities: response.data.municipalities || [],
        institutions: response.data.institutions || [],
        socialMedia: response.data.socialMedia || [],
        institutionalProtocols: response.data.institutionalProtocols || [],
        employeePositions: response.data.employeePositions || [],
        genders: response.data.genders || [],
        originCases: response.data.originCases || []
      };
    } catch (error) {
      console.error('Error al cargar datos maestros consolidados:', error);
      throw error;
    }
  },
};

export default AlertService;
