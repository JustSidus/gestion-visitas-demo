import api from '../api/api';

/**
 * Servicio para manejar las estadísticas y métricas del sistema
 * Consume datos reales desde el backend Laravel
 */
const StatsService = {
  /**
   * Obtiene la tendencia diaria de visitas
   * @param {string} from - Fecha inicio (YYYY-MM-DD)
   * @param {string} to - Fecha fin (YYYY-MM-DD)
   */
  async getDailyTrend(from, to) {
    const response = await api.get('/stats/daily', {
      params: { from, to }
    });
    return response.data;
  },

  /**
   * Obtiene la distribución de visitas por departamento
   * @param {string} from - Fecha inicio (YYYY-MM-DD)
   * @param {string} to - Fecha fin (YYYY-MM-DD)
   */
  async getByDepartment(from, to) {
    const response = await api.get('/stats/by-department', {
      params: { from, to }
    });
    return response.data;
  },

  /**
   * Obtiene la duración promedio de las visitas
   * @param {string} from - Fecha inicio (YYYY-MM-DD)
   * @param {string} to - Fecha fin (YYYY-MM-DD)
   */
  async getAverageDuration(from, to) {
    const response = await api.get('/stats/duration', {
      params: { from, to }
    });
    return response.data;
  },

  /**
   * Obtiene las visitas por hora del día (0-23h)
   * @param {string} from - Fecha inicio (YYYY-MM-DD)
   * @param {string} to - Fecha fin (YYYY-MM-DD)
   */
  async getHourlyPeak(from, to) {
    const response = await api.get('/stats/hourly', {
      params: { from, to }
    });
    return response.data;
  },

  /**
   * Obtiene el promedio de visitas por día de la semana
   * @param {string} from - Fecha inicio (YYYY-MM-DD)
   * @param {string} to - Fecha fin (YYYY-MM-DD)
   */
  async getWeekdayAverage(from, to) {
    const response = await api.get('/stats/weekday-average', {
      params: { from, to }
    });
    return response.data;
  },

  /**
   * Obtiene comparativa semanal (actual vs anterior)
   * @param {string} week - Fecha de inicio de la semana (YYYY-MM-DD)
   */
  async getWeeklyCompare(week) {
    const response = await api.get('/stats/weekly-compare', {
      params: { week }
    });
    return response.data;
  },

  /**
   * Obtiene KPIs generales
   * @param {string} from - Fecha inicio (YYYY-MM-DD)
   * @param {string} to - Fecha fin (YYYY-MM-DD)
   */
  async getKPIs(from, to) {
    const response = await api.get('/stats/kpis', {
      params: { from, to }
    });
    return response.data;
  }
};

export default StatsService;
