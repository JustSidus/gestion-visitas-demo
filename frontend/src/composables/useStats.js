import { ref } from 'vue'
import VisitService from '@/services/VisitService'
import logger from '@/utils/logger'

/**
 * Composable centralizado para manejar estadísticas según el rol del usuario
 * 
 * CONFIGURACIÓN DE ROLES:
 * - admin: Ve TODAS las visitas (misionales + no misionales)
 * - aux_ugc: Ve solo visitas MISIONALES
 * - asist_adm: Ve solo visitas NO MISIONALES
 */

export function useStats() {
  const stats = ref({
    totalVisitors: 0,
    todayVisitors: 0,
    activeVisits: 0,
    // Nuevos: contadores específicos
    activeMissionVisits: 0,
    activeNonMissionVisits: 0
  })

  /**
   * Carga las estadísticas para los badges superiores según el rol del usuario
   * Esta función se usa en TODAS las vistas excepto en el dashboard completo
   * 
   * @returns {Promise<Object>} Objeto con las estadísticas filtradas por rol
   */
  const loadHeaderStats = async () => {
    try {
      const userDataStr = localStorage.getItem('user')
      let userData = null
      try {
        userData = userDataStr ? JSON.parse(userDataStr) : null
      } catch {
        userData = null
      }
      
      if (!userData || !userData.role) {
        logger.warn('No se pudo obtener el rol del usuario')
        return stats.value
      }

      const userRole = userData.role.toLowerCase()
      let data = null

      // Cargar estadísticas según el rol
      switch (userRole) {
        case 'admin':
          // Admin ve TODAS las visitas (misionales + no misionales)
          data = await VisitService.getDashboardStats()
          break
        
        case 'aux_ugc':
          // Auxiliar UGC ve solo visitas MISIONALES
          data = await VisitService.getMissionStatsOnly()
          break
        
        case 'asist_adm':
          // Asistente Administrativo ve solo visitas NO MISIONALES
          data = await VisitService.getNonMissionStatsOnly()
          break
        
        default:
          // Por defecto, cargar todas las estadísticas
          logger.warn(`Rol desconocido: ${userRole}, cargando todas las estadísticas`)
          data = await VisitService.getDashboardStats()
          break
      }

      // Actualizar stats: manejar distintos shapes de respuesta
      stats.value = {
        totalVisitors: data.total_visitors_this_week || 0,
        todayVisitors: data.today_visitors || 0,
        // activeVisits puede venir como 'active_visits' (misional o total) o 'active_visits' + 'active_mission_visits' en dashboard completo
        activeVisits: data.active_visits ?? data.active_visits ?? 0,
        // Si la respuesta contiene active_mission_visits o proviene de getMissionStatsOnly
        activeMissionVisits: data.active_mission_visits ?? data.active_visits ?? 0,
        // Si la respuesta contiene active_non_mission_visits (dashboard completo) o se puede derivar
        activeNonMissionVisits: data.active_non_mission_visits ?? Math.max((data.active_visits ?? 0) - (data.active_mission_visits ?? 0), 0)
      }

      return stats.value
    } catch (error) {
      logger.error('Error al cargar estadísticas del header', error)
      return stats.value
    }
  }

  /**
   * Carga estadísticas para la vista de Visitas Activas según el rol
   * Admin ve todas, asist_adm ve solo no misionales, aux_ugc ve solo misionales
   * 
   * @returns {Promise<Object>} Objeto con las estadísticas para tarjetas grandes
   */
  const loadActiveVisitsStats = async () => {
    try {
      const userDataStr = localStorage.getItem('user')
      let userData = null
      try {
        userData = userDataStr ? JSON.parse(userDataStr) : null
      } catch {
        userData = null
      }
      
      if (!userData || !userData.role) {
        logger.warn('No se pudo obtener el rol del usuario')
        return null
      }

      const userRole = userData.role.toLowerCase()
      let data = null

      // Cargar estadísticas según el rol
      switch (userRole) {
        case 'admin':
          // Admin ve TODAS las estadísticas
          data = await VisitService.getDashboardStats()
          break
        
        case 'aux_ugc':
          // Auxiliar UGC ve solo visitas MISIONALES
          data = await VisitService.getMissionStatsOnly()
          break
        
        case 'asist_adm':
          // Asistente Administrativo ve solo visitas NO MISIONALES
          data = await VisitService.getNonMissionStatsOnly()
          break
        
        default:
          logger.warn(`Rol desconocido: ${userRole}, cargando todas las estadísticas`)
          data = await VisitService.getDashboardStats()
          break
      }

      return data
    } catch (error) {
      logger.error('Error al cargar estadísticas de visitas activas', error)
      return null
    }
  }

  /**
   * Carga estadísticas FIJAS para la vista de Visitas Misionales
   * TODOS los roles ven solo estadísticas de visitas MISIONALES
   * 
   * @returns {Promise<Object>} Objeto con las estadísticas solo de visitas misionales
   */
  const loadMissionVisitsStats = async () => {
    try {
      // FIJO: Todos los roles ven solo estadísticas MISIONALES
      const data = await VisitService.getMissionStatsOnly()
      return data
    } catch (error) {
      logger.error('Error al cargar estadísticas de visitas misionales', error)
      return null
    }
  }

  return {
    stats,
    loadHeaderStats,
    loadActiveVisitsStats,
    loadMissionVisitsStats
  }
}
