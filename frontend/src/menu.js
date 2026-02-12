// Institución Demo - Menu Navigation Structure
export const MENU = [
  {
    icon: 'mdi:account-multiple-check',
    label: 'Visitas Activas',
    to: '/visits',
    roles: ['Admin', 'Asist_adm', 'Guardia']
  },
  {
    icon: 'mdi:briefcase',
    label: 'Visitas Misionales',
    to: '/mission-visits',
    roles: ['Admin', 'aux_ugc']
  },
  {
    icon: 'mdi:account-plus',
    label: 'Registrar Visita',
    to: '/crear-visitas',
    roles: ['Admin', 'Asist_adm']
  },
  {
    icon: 'mdi:history',
    label: 'Historial',
    to: '/historial',
    roles: ['Admin', 'Asist_adm']
  },
  {
    icon: 'mdi:view-dashboard',
    label: 'Estadísticas',
    to: '/statistics',
    roles: ['Admin', 'Asist_adm']
  },
  {
    icon: 'mdi:account-cog',
    label: 'Usuarios',
    to: '/user-management',
    roles: ['Admin']
  }
];

// Quick actions for Command Palette
export const QUICK_ACTIONS = [
  {
    id: 'nueva-visita',
    label: 'Registrar nueva visita',
    icon: 'mdi:account-plus',
    to: '/crear-visitas',
    roles: ['Admin', 'Asist_adm']
  },
  {
    id: 'ver-estadisticas',
    label: 'Ver estadísticas',
    icon: 'mdi:chart-bar',
    to: '/statistics',
    roles: ['Admin', 'Asist_adm']
  },
  {
    id: 'buscar-visita',
    label: 'Buscar visita',
    icon: 'mdi:magnify',
    action: 'search-visit'
  }
];
