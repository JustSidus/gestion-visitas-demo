<template>
  <AppLayout :stats="stats">
    <div class="max-w-7xl mx-auto space-y-8">
    <!-- Header con título -->
    <div class="text-left">
      <h1 class="text-2xl font-bold text-gray-900">Gestión de Usuarios</h1>
      <p class="text-sm text-gray-600 mt-1">Administra usuarios de la aplicación y busca en Microsoft 365</p>
    </div>

    <!-- Tabs: Usuarios de la App | Buscar en Microsoft 365 -->
    <div>
      <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8 px-0">
          <button
            @click="activeTab = 'app-users'"
            :class="[
              activeTab === 'app-users'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2'
            ]"
          >
            <Icon icon="mdi:account-group" class="w-5 h-5" />
            Usuarios de la App ({{ appUsers.length }})
          </button>
          <button
            @click="activeTab = 'microsoft-search'"
            :class="[
              activeTab === 'microsoft-search'
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2'
            ]"
          >
            <Icon icon="mdi:microsoft" class="w-5 h-5" />
            Buscar en Microsoft 365
          </button>
        </nav>
      </div>

      <!-- Tab: Usuarios de la App -->
      <div v-show="activeTab === 'app-users'" class="space-y-4 px-0">
        <!-- Filtro de búsqueda -->
        <div class="flex items-center gap-4">
          <div class="flex-1 relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <Icon icon="mdi:magnify" class="w-5 h-5 text-gray-400" />
            </div>
            <input
              v-model="appUserSearch"
              type="text"
              placeholder="Buscar por nombre o correo..."
              class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
            />
          </div>
          <button
            @click="fetchAppUsers"
            class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all shadow-sm hover:shadow-md flex items-center gap-2 font-medium"
          >
            <Icon icon="mdi:refresh" class="w-5 h-5" />
            Actualizar
          </button>
        </div>

        <!-- Loading -->
        <div v-if="isLoadingAppUsers" class="flex justify-center items-center py-12">
          <Skeleton type="table" :rows="5" />
        </div>

        <!-- Tabla de usuarios -->
        <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-visible">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Usuario</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Rol</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Estado</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Registrado</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Acciones</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="user in filteredAppUsers" :key="user.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-11 w-11 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-sm">
                      <span class="text-white font-semibold text-sm">{{ getInitials(user.name) }}</span>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-semibold text-gray-900">{{ user.name }}</div>
                      <div class="text-sm text-gray-500 flex items-center gap-1">
                        <Icon icon="mdi:email" class="w-4 h-4" />
                        {{ user.email }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4" :style="{ position: 'relative', zIndex: openDropdownId === user.id ? 50 : 1 }">
                  <div class="role-dropdown-wrapper">
                    <!-- Botón del dropdown -->
                    <button
                      type="button"
                      @click="toggleDropdown(user.id)"
                      @blur="closeDropdownDelayed(user.id)"
                      class="w-full px-4 py-2.5 pr-10 text-sm font-medium text-gray-700 bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all cursor-pointer hover:from-gray-100 hover:to-gray-200 text-left flex items-center justify-between"
                    >
                      <span>{{ getRoleName(user.role_id) }}</span>
                      <Icon 
                        icon="mdi:chevron-down" 
                        class="w-5 h-5 text-gray-500 transition-transform duration-200"
                        :class="{ 'rotate-180': openDropdownId === user.id }"
                      />
                    </button>
                    
                    <!-- Menú desplegable -->
                    <transition
                      enter-active-class="transition ease-out duration-100"
                      enter-from-class="transform opacity-0 scale-95"
                      enter-to-class="transform opacity-100 scale-100"
                      leave-active-class="transition ease-in duration-75"
                      leave-from-class="transform opacity-100 scale-100"
                      leave-to-class="transform opacity-0 scale-95"
                    >
                      <div
                        v-show="openDropdownId === user.id"
                        class="absolute w-full mt-2 top-full bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden"
                        style="z-index: 100;"
                      >
                        <div class="py-1 max-h-60 overflow-y-auto custom-scrollbar">
                          <button
                            v-for="role in roles"
                            :key="role.id"
                            type="button"
                            @mousedown.prevent="selectRole(user, role.id)"
                            :class="[
                              'w-full px-4 py-2.5 text-left text-sm font-medium transition-all',
                              user.role_id === role.id
                                ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white'
                                : 'text-gray-700 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50'
                            ]"
                          >
                            <div class="flex items-center justify-between">
                              <span>{{ role.name }}</span>
                              <Icon 
                                v-if="user.role_id === role.id"
                                icon="mdi:check-circle" 
                                class="w-5 h-5"
                              />
                            </div>
                          </button>
                        </div>
                      </div>
                    </transition>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <button
                    @click="toggleUserActive(user)"
                    :class="[
                      user.is_active
                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                        : 'bg-red-100 text-red-700 hover:bg-red-200',
                      'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5'
                    ]"
                    :title="user.is_active ? 'Click para desactivar' : 'Click para activar'"
                  >
                    <Icon 
                      :icon="user.is_active ? 'mdi:check-circle' : 'mdi:close-circle'" 
                      class="w-4 h-4"
                    />
                    {{ user.is_active ? 'Activo' : 'Inactivo' }}
                  </button>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">
                  <div class="flex items-center gap-1">
                    <Icon icon="mdi:calendar" class="w-4 h-4 text-gray-400" />
                    {{ formatDate(user.created_at) }}
                  </div>
                </td>
                <td class="px-6 py-4 text-right">
                  <button
                    @click="confirmDeleteUser(user)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-red-600 hover:text-white hover:bg-red-600 border border-red-600 rounded-lg transition-all text-sm font-medium"
                    title="Eliminar usuario"
                  >
                    <Icon icon="mdi:delete" class="w-4 h-4" />
                    Eliminar
                  </button>
                </td>
              </tr>
              <tr v-if="filteredAppUsers.length === 0">
                <td colspan="5" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center gap-3">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                      <Icon icon="mdi:account-search" class="w-8 h-8 text-gray-400" />
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-900">No se encontraron usuarios</p>
                      <p class="text-sm text-gray-500 mt-1">Intenta con otro término de búsqueda</p>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab: Buscar en Microsoft 365 -->
      <div v-show="activeTab === 'microsoft-search'" class="space-y-4 px-0">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 mb-4">
          <div class="flex items-start gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <Icon icon="mdi:information" class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <p class="text-sm font-semibold text-blue-900 mb-1">Búsqueda en Microsoft 365</p>
              <p class="text-sm text-blue-800">
                Busca usuarios en tu organización de Microsoft 365 y agrégalos a la aplicación.
                Solo los usuarios agregados podrán iniciar sesión.
              </p>
            </div>
          </div>
        </div>

        <!-- Formulario de búsqueda -->
        <div class="flex items-center gap-4">
          <div class="flex-1 relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <Icon icon="mdi:magnify" class="w-5 h-5 text-gray-400" />
            </div>
            <input
              v-model="microsoftSearch"
              @keyup.enter="searchMicrosoftUsers"
              type="text"
              placeholder="Buscar por nombre o correo en Microsoft 365..."
              class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
            />
          </div>
          <button
            @click="searchMicrosoftUsers"
            :disabled="!microsoftSearch.trim() || isLoadingMicrosoftUsers"
            class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md flex items-center gap-2 font-medium"
          >
            <Icon icon="mdi:magnify" class="w-5 h-5" />
            Buscar
          </button>
        </div>

        <!-- Loading -->
        <div v-if="isLoadingMicrosoftUsers" class="flex justify-center items-center py-12">
          <Skeleton type="table" :rows="3" />
        </div>

        <!-- Resultados de Microsoft -->
        <div v-else-if="microsoftUsers.length > 0" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Usuario</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Departamento</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Estado</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Acción</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="user in microsoftUsers" :key="user.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-11 w-11 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-sm">
                      <span class="text-white font-semibold text-sm">{{ getInitials(user.displayName) }}</span>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-semibold text-gray-900">{{ user.displayName }}</div>
                      <div class="text-sm text-gray-500 flex items-center gap-1">
                        <Icon icon="mdi:email" class="w-4 h-4" />
                        {{ user.mail || user.userPrincipalName }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-1 text-sm text-gray-600">
                    <Icon icon="mdi:briefcase" class="w-4 h-4 text-gray-400" />
                    {{ user.department || user.jobTitle || 'N/A' }}
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span
                    v-if="isUserInApp(user)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-semibold"
                  >
                    <Icon icon="mdi:check-circle" class="w-4 h-4 text-green-600" />
                    Ya está en la app
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded-lg text-xs font-semibold"
                  >
                    <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                    No registrado
                  </span>
                </td>
                <td class="px-6 py-4 text-right">
                  <button
                    v-if="!isUserInApp(user)"
                    @click="showAddUserModal(user)"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all shadow-sm hover:shadow-md text-sm font-medium"
                  >
                    <Icon icon="mdi:account-plus" class="w-4 h-4" />
                    Agregar a la App
                  </button>
                  <span v-else class="text-sm text-gray-400 flex items-center justify-end gap-1">
                    <Icon icon="mdi:check" class="w-4 h-4" />
                    Ya registrado
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Sin resultados -->
        <div v-else-if="microsoftSearchPerformed" class="text-center py-16 bg-white rounded-xl shadow-sm border border-gray-100">
          <div class="flex flex-col items-center gap-4">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center">
              <Icon icon="mdi:account-search" class="w-10 h-10 text-gray-400" />
            </div>
            <div>
              <h3 class="text-base font-semibold text-gray-900">No se encontraron usuarios</h3>
              <p class="mt-2 text-sm text-gray-500">Intenta con otro término de búsqueda</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Agregar Usuario -->
    <AppModal
      v-if="showAddUserModalState"
      title="Agregar Usuario a la Aplicación"
      @close="showAddUserModalState = false"
    >
      <div class="space-y-4">
        <!-- Info del usuario -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200">
          <div class="flex items-center gap-3">
            <div class="flex-shrink-0 h-14 w-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-md">
              <span class="text-white font-bold text-base">{{ getInitials(selectedMicrosoftUser?.displayName) }}</span>
            </div>
            <div>
              <div class="font-semibold text-gray-900">{{ selectedMicrosoftUser?.displayName }}</div>
              <div class="text-sm text-gray-600 flex items-center gap-1 mt-1">
                <Icon icon="mdi:email" class="w-4 h-4" />
                {{ selectedMicrosoftUser?.mail || selectedMicrosoftUser?.userPrincipalName }}
              </div>
            </div>
          </div>
        </div>

        <!-- Selector de rol -->
        <div>
          <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
            <Icon icon="mdi:account-badge" class="w-5 h-5 text-blue-600" />
            Asignar Rol <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <select
              v-model="newUserRole"
              class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-medium bg-white appearance-none cursor-pointer hover:border-gray-400 transition-all"
            >
              <option value="">-- Selecciona un rol --</option>
              <option v-for="role in roles" :key="role.id" :value="role.id">
                {{ role.name }}
              </option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
              <Icon icon="mdi:chevron-down" class="w-5 h-5 text-gray-500" />
            </div>
          </div>
          <p class="mt-2 text-xs text-gray-500 flex items-center gap-1">
            <Icon icon="mdi:information-outline" class="w-4 h-4" />
            Elige el rol que tendrá este usuario en la aplicación
          </p>
        </div>

        <!-- Descripción de roles -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-900 space-y-2">
          <div class="flex items-start gap-2">
            <Icon icon="mdi:shield-crown" class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
            <div>
              <span class="font-semibold">Admin:</span> Acceso total, gestiona usuarios
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Icon icon="mdi:clipboard-text" class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
            <div>
              <span class="font-semibold">Asist_adm:</span> Registra y cierra visitas, ve estadísticas
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Icon icon="mdi:shield-account" class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
            <div>
              <span class="font-semibold">Guardia:</span> Solo ve visitas activas y valida QR
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Icon icon="mdi:account-cog" class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
            <div>
              <span class="font-semibold">Aux_ugc:</span> Auxiliar de la UGC
            </div>
          </div>
        </div>

        <!-- Error message -->
        <div v-if="addUserError" class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3">
          <Icon icon="mdi:alert-circle" class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
          <p class="text-sm text-red-800">{{ addUserError }}</p>
        </div>

        <!-- Botones -->
        <div class="flex justify-end gap-3 pt-4 border-t">
          <button
            @click="showAddUserModalState = false"
            class="px-5 py-2.5 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-all font-medium flex items-center gap-2"
          >
            <Icon icon="mdi:close" class="w-4 h-4" />
            Cancelar
          </button>
          <button
            @click="addUserToApp"
            :disabled="!newUserRole || isAddingUser"
            class="px-6 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md font-medium flex items-center gap-2"
          >
            <Icon v-if="isAddingUser" icon="mdi:loading" class="w-5 h-5 animate-spin" />
            <Icon v-else icon="mdi:check-circle" class="w-5 h-5" />
            <span v-if="isAddingUser">Agregando...</span>
            <span v-else>Agregar Usuario</span>
          </button>
        </div>
      </div>
    </AppModal>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Icon } from '@iconify/vue';
import { userManagementAPI } from '@/api/api';
import AppLayout from '@/components/layouts/AppLayout.vue';
import { Skeleton, AppModal } from '@/components/UI';
import Swal from 'sweetalert2';
import logger from '../utils/logger';
import VisitService from '@/services/VisitService';
import { useStats } from '@/composables/useStats';

// Composable para manejar estadísticas según el rol
const { stats, loadHeaderStats } = useStats();

// Tabs
const activeTab = ref('app-users');

// Usuarios de la App
const appUsers = ref([]);
const appUserSearch = ref('');
const isLoadingAppUsers = ref(false);

// Búsqueda en Microsoft
const microsoftUsers = ref([]);
const microsoftSearch = ref('');
const microsoftSearchPerformed = ref(false);
const isLoadingMicrosoftUsers = ref(false);

// Modal agregar usuario
const showAddUserModalState = ref(false);
const selectedMicrosoftUser = ref(null);
const newUserRole = ref('');
const addUserError = ref('');
const isAddingUser = ref(false);

// Roles disponibles (se cargan dinámicamente de la BD)
const roles = ref([]);

// Control del dropdown personalizado
const openDropdownId = ref(null);
let closeTimeout = null;

// Obtener nombre del rol
const getRoleName = (roleId) => {
  const role = roles.value.find(r => r.id === roleId);
  return role ? role.name : 'Sin rol';
};

// Toggle dropdown
const toggleDropdown = (userId) => {
  if (closeTimeout) {
    clearTimeout(closeTimeout);
    closeTimeout = null;
  }
  openDropdownId.value = openDropdownId.value === userId ? null : userId;
};

// Cerrar dropdown con delay
const closeDropdownDelayed = (userId) => {
  closeTimeout = setTimeout(() => {
    if (openDropdownId.value === userId) {
      openDropdownId.value = null;
    }
  }, 200);
};

// Seleccionar rol
const selectRole = async (user, newRoleId) => {
  // Cerrar el dropdown inmediatamente
  openDropdownId.value = null;
  
  // Si el rol no cambió, no hacer nada
  if (newRoleId === user.role_id) return;
  
  const result = await Swal.fire({
    title: '¿Cambiar rol de usuario?',
    html: `¿Estás seguro de cambiar el rol de <strong>${user.name}</strong>?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Sí, cambiar',
    cancelButtonText: 'Cancelar'
  });

  if (result.isConfirmed) {
    await updateUserRole(user, newRoleId);
  }
};

// Computed: Usuarios filtrados
const filteredAppUsers = computed(() => {
  if (!appUserSearch.value.trim()) return appUsers.value;
  
  const search = appUserSearch.value.toLowerCase();
  return appUsers.value.filter(user => 
    user.name.toLowerCase().includes(search) ||
    user.email.toLowerCase().includes(search)
  );
});

// Obtener iniciales
const getInitials = (name) => {
  if (!name) return '?';
  const names = name.split(' ');
  if (names.length >= 2) {
    return (names[0][0] + names[1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
};

// Formatear fecha
const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString('es-DO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

// Verificar si usuario ya está en la app
const isUserInApp = (microsoftUser) => {
  const email = microsoftUser.mail || microsoftUser.userPrincipalName;
  return appUsers.value.some(user => user.email === email);
};

// Fetch: Roles disponibles
const fetchRoles = async () => {
  try {
    const response = await userManagementAPI.getRoles();
    roles.value = response.data.roles;
  } catch (error) {
    logger.error('Error al obtener roles', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudieron cargar los roles',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
  }
};

// Fetch: Usuarios de la app
const fetchAppUsers = async () => {
  isLoadingAppUsers.value = true;
  try {
    const response = await userManagementAPI.getUsers();
    appUsers.value = response.data.users;
  } catch (error) {
    logger.error('Error al obtener usuarios de la aplicación', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudieron cargar los usuarios de la aplicación',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
  } finally {
    isLoadingAppUsers.value = false;
  }
};

// Buscar en Microsoft 365
const searchMicrosoftUsers = async () => {
  if (!microsoftSearch.value.trim()) return;
  
  isLoadingMicrosoftUsers.value = true;
  microsoftSearchPerformed.value = true;
  try {
    const response = await userManagementAPI.searchMicrosoftUsers(microsoftSearch.value);
    microsoftUsers.value = response.data.users;
  } catch (error) {
    logger.error('Error al buscar usuarios en Microsoft 365', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.error || 'No se pudo buscar en Microsoft 365',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
    microsoftUsers.value = [];
  } finally {
    isLoadingMicrosoftUsers.value = false;
  }
};

// Mostrar modal agregar usuario
const showAddUserModal = (user) => {
  selectedMicrosoftUser.value = user;
  newUserRole.value = '';
  addUserError.value = '';
  showAddUserModalState.value = true;
};

// Agregar usuario a la app
const addUserToApp = async () => {
  if (!newUserRole.value) {
    addUserError.value = 'Por favor selecciona un rol';
    return;
  }

  isAddingUser.value = true;
  addUserError.value = '';

  try {
    const email = selectedMicrosoftUser.value.mail || selectedMicrosoftUser.value.userPrincipalName;
    
    await userManagementAPI.addUser({
      email: email,
      name: selectedMicrosoftUser.value.displayName,
      microsoft_id: selectedMicrosoftUser.value.id,
      role_id: newUserRole.value
    });

    Swal.fire({
      icon: 'success',
      title: ' Usuario agregado',
      text: `${selectedMicrosoftUser.value.displayName} ha sido agregado exitosamente`,
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });

    showAddUserModalState.value = false;
    await fetchAppUsers();
    
    // Actualizar lista de Microsoft para reflejar cambio
    await searchMicrosoftUsers();
    
  } catch (error) {
    logger.error('Error al agregar usuario', error);
    
    // Mostrar detalles de validación si existen
    if (error.response?.data?.details) {
      const details = error.response.data.details;
      const errorMessages = Object.values(details).flat().join(', ');
      addUserError.value = `Error de validación: ${errorMessages}`;
    } else {
      addUserError.value = error.response?.data?.error || error.response?.data?.message || 'No se pudo agregar el usuario';
    }
  } finally {
    isAddingUser.value = false;
  }
};

// Actualizar rol de usuario
const updateUserRole = async (user, newRoleId) => {
  try {
    await userManagementAPI.updateUser(user.id, { role_id: newRoleId });
    
    Swal.fire({
      icon: 'success',
      title: 'Rol actualizado',
      text: 'El rol del usuario ha sido cambiado exitosamente',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2000,
      timerProgressBar: true
    });
    
    // Actualizar la lista de usuarios
    await fetchAppUsers();
  } catch (error) {
    logger.error('Error al actualizar rol', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudo actualizar el rol',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
    
    // Recargar para asegurar que los datos sean correctos
    await fetchAppUsers();
  }
};

// Toggle estado activo/inactivo
const toggleUserActive = async (user) => {
  try {
    const action = user.is_active ? 'desactivar' : 'activar';
    
    const result = await Swal.fire({
      title: `¿${action.charAt(0).toUpperCase() + action.slice(1)} usuario?`,
      text: `¿Estás seguro de ${action} a ${user.name}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: user.is_active ? '#d33' : '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: `Sí, ${action}`,
      cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
      await userManagementAPI.toggleUserActive(user.id);
      
      Swal.fire({
        icon: 'success',
        title: `Usuario ${user.is_active ? 'desactivado' : 'activado'}`,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000
      });
      
      await fetchAppUsers();
    }
  } catch (error) {
    logger.error('Error al cambiar estado', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudo cambiar el estado del usuario',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
  }
};

// Confirmar eliminar usuario
const confirmDeleteUser = async (user) => {
  const result = await Swal.fire({
    title: '¿Eliminar usuario?',
    html: `<p>¿Estás seguro de eliminar a <strong>${user.name}</strong>?</p>
           <p class="text-sm text-red-600 mt-2">Esta acción no se puede deshacer.</p>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '️ Sí, eliminar',
    cancelButtonText: 'Cancelar'
  });

  if (result.isConfirmed) {
    try {
      await userManagementAPI.deleteUser(user.id);
      
      Swal.fire({
        icon: 'success',
        title: 'Usuario eliminado',
        text: `${user.name} ha sido eliminado de la aplicación`,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
      });
      
      await fetchAppUsers();
    } catch (error) {
      logger.error('Error al eliminar usuario', error);
      
      // Extraer mensaje específico del error del backend
      const errorMessage = error.response?.data?.message || 'No se pudo eliminar el usuario';
      const errorCode = error.response?.data?.code;
      
      // Mostrar mensaje más largo para ciertos códigos de error
      const showConfirmButton = errorCode === 'CANNOT_DELETE_OWN_ACCOUNT';
      
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: errorMessage,
        toast: !showConfirmButton,
        position: showConfirmButton ? 'center' : 'top-end',
        showConfirmButton: showConfirmButton,
        confirmButtonText: 'Entendido',
        timer: showConfirmButton ? undefined : 3000
      });
    }
  }
};

// Cargar datos al montar
onMounted(() => {
  fetchRoles();
  fetchAppUsers();
  loadHeaderStats();
});

// Cleanup de timers al desmontar
onUnmounted(() => {
  if (closeTimeout) {
    clearTimeout(closeTimeout);
    closeTimeout = null;
  }
});
</script>

<style scoped>
/* Estilos para el dropdown personalizado de roles */
.role-dropdown-wrapper {
  position: relative;
}

/* Scrollbar personalizado para el dropdown */
.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 #f8fafc;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: #f8fafc;
  border-radius: 8px;
  margin: 4px 0;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #cbd5e1 0%, #94a3b8 100%);
  border-radius: 8px;
  border: 1px solid #f8fafc;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%);
}

/* Animación del chevron */
.rotate-180 {
  transform: rotate(180deg);
}
</style>

