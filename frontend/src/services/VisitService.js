import api from "../api/api";

export default {
  // Obtener todas las visitas con relaciones
  async getAll() {
    const response = await api.get("/visits");
    return response.data;
  },
  
  // NUEVO: Obtener solo visitas activas (abiertas)
  async getActiveVisits(search = null) {
    const params = {};
    if (search) {
      params.q = search;
    }
    const response = await api.get("/visits-active", { params });
    return response.data;
  },
  
  // NUEVO: Obtener visitas activas misionales
  async getActiveMissionVisits(search = null) {
    const params = {};
    if (search) {
      params.q = search;
    }
    const response = await api.get("/visits-active-mission", { params });
    return response.data;
  },
  
  // NUEVO: Obtener visitas activas NO misionales
  async getActiveNonMissionVisits(search = null) {
    const params = {};
    if (search) {
      params.q = search;
    }
    const response = await api.get("/visits-active-non-mission", { params });
    return response.data;
  },
  
  // NUEVO: Obtener visitas cerradas de hoy
  async getClosedTodayVisits(search = null) {
    const params = {};
    if (search) {
      params.q = search;
    }
    const response = await api.get("/visits-closed-today", { params });
    return response.data;
  },
  
  // Deprecado: se mantiene por compatibilidad
  async todayVisits(search = null) {
    const params = {};
    if (search) {
      params.q = search;
    }
    const response = await api.get("/visits-today", { params });
    return response.data;
  },
  
  // Deprecado: se mantiene por compatibilidad
  async pastVisits() {
    const response = await api.get("/visits-closed-today");
    return response.data;
  },

  // Obtener visitas paginadas
  async getPaginated(page = 1) {
    const response = await api.get(`/visits?page=${page}`);
    return response.data;
  },

  // Crear una nueva visita
  async create(data) {
    const response = await api.post("/visits", data);
    return response.data;
  },

  // Obtener una visita específica con sus relaciones
  async getById(id) {
    const response = await api.get(`/visits/${id}`);
    return response.data;
  },

  // Actualizar una visita
  async update(id, data) {
    const response = await api.put(`/visits/${id}`, data);
    return response.data;
  },

  // Eliminar una visita
  async delete(id) {
    const response = await api.delete(`/visits/${id}`);
    return response.data;
  },

  // Buscar visitas por filtros (status, fecha, cédula)
  async search(params) {
    const response = await api.get("/visits-search", { params });
    return response.data;
  },

  // Buscar visitas por filtros (status, fecha, cédula)
  async closeVisit(id, data) {
    return await api.put(`/visits/${id}/close`, data);
  },

  // Búsqueda avanzada con múltiples filtros
  async advancedSearch(filters) {
    const response = await api.get("/visits-advanced-search", { params: filters });
    return response.data;
  },

  // Exportar a Excel
  async exportToExcel(filters) {
    const response = await api.get("/visits-export-excel", { 
      params: filters,
      responseType: 'blob'
    });
    return response.data;
  },

  // Exportar a PDF
  async exportToPDF(filters) {
    const response = await api.get("/visits-export-pdf", { 
      params: filters,
      responseType: 'blob'
    });
    return response.data;
  },

  // Obtener estadísticas del dashboard
  async getDashboardStats() {
    const response = await api.get("/dashboard/stats");
    return response.data;
  },

  // Obtener estadísticas del dashboard solo para visitas misionales
  async getMissionStatsOnly() {
    const response = await api.get("/dashboard/stats/mission");
    return response.data;
  },

  // Obtener estadísticas del dashboard solo para visitas NO misionales
  async getNonMissionStatsOnly() {
    const response = await api.get("/dashboard/stats/non-mission");
    return response.data;
  }

};
