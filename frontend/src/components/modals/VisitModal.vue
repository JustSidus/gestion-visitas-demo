<!--
  ╔══════════════════════════════════════════════════════════════════════════════╗
  ║ VisitModal.vue - Modal Especializado para Detalles de Visitas               ║
  ║                                                                              ║
  ║ PROPÓSITO:                                                                   ║
  ║ Modal dedicado exclusivamente para mostrar información completa de visitas  ║
  ║ tanto activas como cerradas. Permite visualizar detalles del visitante,     ║
  ║ información de la visita, gestión de placa vehicular y cerrar visitas.      ║
  ║                                                                              ║
  ║ USADO EN:                                                                    ║
  ║ • ActiveVisits.vue - Ver y cerrar visitas activas                           ║
  ║ • VisitHistory.vue - Consultar detalles de visitas cerradas                 ║
  ║                                                                              ║
  ║ CARACTERÍSTICAS:                                                             ║
  ║  Visualización completa de datos del visitante                             ║
  ║  Información de persona visitada, departamento, edificio, piso             ║
  ║  Gestión de placa vehicular (Admin/Guardia en visitas activas)             ║
  ║  Validación de formato de placas (1-2 letras + 3-6 números)                ║
  ║  Cierre de visitas con registro de hora de salida                          ║
  ║  Identificación visual de casos misionales                                 ║
  ║  Diseño institucional responsive                                           ║
  ║                                                                              ║
  ║ PERMISOS:                                                                    ║
  ║ • Admin/Guardia: Pueden editar placa y cerrar visitas                       ║
  ║ • Asist_adm: Solo lectura de visitas cerradas                               ║
  ║                                                                              ║
  ║ AUTOR: Sistema Institución Demo - Gestión de Visitas                        ║
  ║ ÚLTIMA ACTUALIZACIÓN: 2025-11-13                                            ║
  ╚══════════════════════════════════════════════════════════════════════════════╝
-->

<template>
  <Teleport to="body">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black bg-opacity-50 z-[9999]" @click="$emit('close')"></div>
    <!-- Modal Content -->
    <div class="fixed inset-0 flex items-center justify-center p-4 z-[10000] pointer-events-none">
      <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 shadow-xl relative pointer-events-auto" @click.stop>
        
        <!-- Header -->
      <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center" 
            :class="{ 'bg-green-100': visit.status_id === 1, 'bg-red-100': visit.status_id !== 1 }">
            <i class="bx text-xl" 
              :class="{ 
                'bx-door-open text-green-600': visit.status_id === 1, 
                'bx-door-closed text-red-600': visit.status_id !== 1 
              }"></i>
          </div>
          <div>
            <h3 class="text-xl font-semibold text-gray-900">
              {{ visit.status_id === 1 ? 'Detalles de Visita' : 'Visita Cerrada' }}
            </h3>
            <p class="text-sm text-gray-500">ID: #{{ visit.id }}</p>
          </div>
        </div>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 transition">
          <i class='bx bx-x text-3xl'></i>
        </button>
      </div>

      <!-- Badge de estado -->
      <div class="mb-6 inline-flex items-center gap-2 px-4 py-2 rounded-lg" 
        :class="{ 'bg-green-50 text-green-700': visit.status_id === 1, 'bg-red-50 text-red-700': visit.status_id !== 1 }">
        <i class="bx" :class="{ 'bx-check-circle': visit.status_id === 1, 'bx-x-circle': visit.status_id !== 1 }"></i>
        <span class="font-medium">{{ visit.status_id === 1 ? 'Visita Activa' : 'Visita Finalizada' }}</span>
      </div>
    
      <!-- SECCIÓN 1: INFORMACIÓN DEL VISITANTE -->
      <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
        <h4 class="text-sm font-semibold text-blue-900 mb-3 flex items-center gap-2">
          <i class='bx bx-user'></i>
          Información del Visitante
        </h4>
        <div class="space-y-2">
          <div>
            <p class="text-lg font-semibold text-gray-900">{{ getFullName(visit) }}</p>
            <!-- Formato del documento según tipo: Cédula/Pasaporte: número, Sin Identificación sin número -->
            <p class="text-sm text-gray-600">
              <span v-if="visit.visitors && visit.visitors[0] && Number(visit.visitors[0].document_type) === 1">
                Cédula: {{ visit.visitors[0].identity_document }}
              </span>
              <span v-else-if="visit.visitors && visit.visitors[0] && Number(visit.visitors[0].document_type) === 2">
                Pasaporte: {{ visit.visitors[0].identity_document }}
              </span>
              <span v-else-if="visit.visitors && visit.visitors[0] && Number(visit.visitors[0].document_type) === 3">
                Sin Identificación
              </span>
              <span v-else-if="visit.visitors && visit.visitors[0]">
                {{ visit.visitors[0].document_type_label || 'Cédula' }}: {{ visit.visitors[0].identity_document || '—' }}
              </span>
              <span v-else>
                No disponible
              </span>
            </p>
          </div>
          <div v-if="getNum(visit) || getMail(visit) || getInstitution(visit)" class="pt-2 border-t border-blue-200 space-y-1">
            <p v-if="getNum(visit)" class="text-sm text-gray-700 flex items-center gap-2">
              <i class='bx bx-phone text-blue-600'></i>
              {{ getNum(visit) }}
            </p>
            <p v-if="getMail(visit)" class="text-sm text-gray-700 flex items-center gap-2">
              <i class='bx bx-envelope text-blue-600'></i>
              {{ getMail(visit) }}
            </p>
            <p v-if="getInstitution(visit)" class="text-sm text-gray-700 flex items-center gap-2">
              <i class='bx bx-building text-blue-600'></i>
              {{ getInstitution(visit) }}
            </p>
          </div>
        </div>
      </div>

      <!-- SECCIÓN 2: DETALLES DE LA VISITA -->
      <div class="mb-5">
        <h4 class="text-base font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <i class='bx bx-info-circle text-lg'></i>
          Detalles de la Visita
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
          <div>
            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Persona a Visitar</label>
            <p class="text-base font-medium text-gray-900 mt-0.5">{{ visit.namePersonToVisit ?? '—' }}</p>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Departamento</label>
            <p class="text-base font-medium text-gray-900 mt-0.5 flex items-center gap-1">
              <i class='bx bx-building text-gray-400'></i>
              {{ visit.department ?? '—' }}
            </p>
          </div>
          <!-- Mostrar edificio solo para casos NO misionales -->
          <div v-if="!visit.mission_case">
            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Edificio</label>
            <p class="text-base font-medium text-gray-900 mt-0.5">{{ visit.building ?? '—' }}</p>
          </div>
          <!-- Mostrar piso solo para casos NO misionales -->
          <div v-if="!visit.mission_case">
            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Piso</label>
            <p class="text-base font-medium text-gray-900 mt-0.5">{{ visit.floor ?? '—' }}</p>
          </div>
          <!-- Mostrar carnet solo para casos NO misionales -->
          <div v-if="!visit.mission_case">
            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Carnet Asignado</label>
            <p class="text-base font-medium text-gray-900 mt-0.5">#{{ getCarnetNumber(visit) }}</p>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Fecha y Hora de Entrada</label>
            <p class="text-base font-medium text-gray-900 mt-0.5">{{ formatDate(visit.created_at_raw || visit.created_at) }}</p>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Fecha y Hora de Salida</label>
            <p class="text-base font-medium text-gray-900 mt-0.5">{{ visit.end_at_raw || visit.end_at ? formatDate(visit.end_at_raw || visit.end_at) : 'Aún activa' }}</p>
          </div>
        </div>
        
        <!-- Motivo (ancho completo) -->
        <div class="mt-3">
          <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Motivo de la Visita</label>
          <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-base text-gray-700 max-h-24 overflow-y-auto mt-0.5">
            {{ visit.reason ?? 'No especificado' }}
          </div>
        </div>
      </div>

      <!-- SECCIÓN 3: CASO MISIONAL (Solo si es true) -->
      <div v-if="visit.mission_case" class="mb-6 p-3 bg-purple-50 border-2 border-purple-300 rounded-lg">
        <div class="flex items-center gap-2">
          <i class='bx bx-briefcase text-purple-600 text-xl'></i>
          <span class="font-semibold text-purple-900">Caso Misional</span>
        </div>
      </div>

      <!-- SECCIÓN 4: VEHÍCULO (Visible para Admin y Guardia SOLO EN VISITAS ACTIVAS) -->
      <div v-if="canEditVehicle && !visit.end_at" class="mb-6">
        <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <i class='bx bx-car'></i>
          Vehículo
        </h4>
        
        <!-- Mostrar placa si ya existe -->
        <div v-if="visit.vehicle_plate && !isEditingVehicle" class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-lg">
          <div class="flex items-center gap-2">
            <i class='bx bx-car text-gray-600'></i>
            <span class="font-medium text-gray-900">{{ visit.vehicle_plate }}</span>
          </div>
          <button 
            @click="startEditingVehicle"
            class="text-blue-600 hover:text-blue-800 text-sm font-medium transition"
          >
            Editar
          </button>
        </div>

        <!-- Formulario para agregar/editar placa -->
        <div v-else class="space-y-3">
          <div class="flex items-center gap-2">
            <input 
              type="checkbox" 
              id="hasVehicle" 
              v-model="hasVehicle"
              class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
            />
            <label for="hasVehicle" class="text-sm font-medium text-gray-700">
              El visitante llegó en vehículo
            </label>
          </div>
          
          <div v-if="hasVehicle" class="space-y-2">
            <input 
              type="text" 
              v-model="vehiclePlate"
              maxlength="7"
              placeholder="Ej: A12345"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase"
              @input="formatVehiclePlate"
            />
            <p class="text-xs text-gray-500">
               Formato: 1-2 letras + 3-6 números (Ej: A12345, AB123456)
            </p>
            <p v-if="vehiclePlateError" class="text-sm text-red-600">{{ vehiclePlateError }}</p>
            <div class="flex gap-2">
              <button 
                @click="saveVehiclePlate"
                :disabled="isSavingVehicle || !isValidVehiclePlate"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
              >
                {{ isSavingVehicle ? 'Guardando...' : 'Guardar' }}
              </button>
              <button 
                v-if="visit.vehicle_plate"
                @click="cancelEditingVehicle"
                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm font-medium"
              >
                Cancelar
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Mostrar placa solo lectura para otros roles -->
      <div v-else-if="visit.vehicle_plate" class="mb-6">
        <h4 class="text-sm font-semibold text-gray-900 mb-2 flex items-center gap-2">
          <i class='bx bx-car'></i>
          Vehículo
        </h4>
        <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg flex items-center gap-2">
          <i class='bx bx-car text-gray-600'></i>
          <span class="font-medium text-gray-900">{{ visit.vehicle_plate }}</span>
        </div>
      </div>

      <!-- Mensaje de confirmación al cerrar (solo si está abierto y no es solo vista) -->
      <div v-if="visit.status_id === 1 && !visit.viewOnly" class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
        <div class="flex items-start gap-2">
          <i class='bx bx-info-circle text-lg mt-0.5'></i>
          <p class="text-sm">¿Está seguro que desea cerrar esta visita? Esta acción registrará la hora de salida actual.</p>
        </div>
      </div>

      <!-- Error general -->
      <div v-if="localError || errorMessage" class="mb-6 bg-red-100 text-red-800 px-4 py-3 rounded-lg">
        <div class="flex items-start gap-2">
          <i class='bx bx-error text-lg mt-0.5'></i>
          <p class="text-sm">{{ localError || errorMessage }}</p>
        </div>
      </div>
    
      <!-- Botones de acción -->
      <div class="flex gap-3 pt-4 border-t border-gray-200">
        <button
          v-if="visit.status_id === 1 && !visit.viewOnly"
          @click="closeVisit"
          :disabled="isClosing"
          class="flex-1 inline-flex justify-center items-center gap-2 rounded-lg px-4 py-2.5 bg-red-600 text-white hover:bg-red-700 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <i class='bx' :class="isClosing ? 'bx-loader-alt bx-spin' : 'bx-log-out'"></i>
          {{ isClosing ? 'Cerrando...' : 'Cerrar Visita' }}
        </button>
        <button
          class="inline-flex justify-center items-center gap-2 rounded-lg px-4 py-2.5 bg-gray-200 text-gray-800 hover:bg-gray-300 transition font-medium"
          :class="{ 'flex-1': !(visit.status_id === 1 && !visit.viewOnly) }"
          @click="$emit('close')"
        >
          <i class='bx bx-x'></i>
          {{ visit.status_id === 1 && !visit.viewOnly ? 'Cancelar' : 'Cerrar' }}
        </button>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, computed } from "vue";
import api from "@/api/api";
import logger from '@/utils/logger';
import { 
  getDocumentType, 
  getIdentityNumber, 
  getFormattedDocument as getFormattedDocumentUtil
} from '@/utils/visitFormatters';

  const props = defineProps({
    visit: Object,
    errorMessage: String,
    isClosing: {
      type: Boolean,
      default: false
    }
  });

  const emit = defineEmits(['close', 'confirm', 'vehicleUpdated']);

  const localError = ref('');
  const hasVehicle = ref(false);
  const vehiclePlate = ref('');
  const vehiclePlateError = ref('');
  const isEditingVehicle = ref(false);
  const isSavingVehicle = ref(false);

  // Determinar si el usuario puede editar el vehículo
  const canEditVehicle = computed(() => {
    try {
      const userData = localStorage.getItem('user');
      if (!userData) return false;
      
      const user = JSON.parse(userData);
      return ['Admin', 'Guardia'].includes(user.role);
    } catch {
      return false;
    }
  });

  /**
   * Valida si la placa vehicular cumple el formato:
   * 1-2 letras mayúsculas + 3-6 números
   * Máximo 7 caracteres totales
   */
  const isValidVehiclePlate = computed(() => {
    if (!hasVehicle.value || !vehiclePlate.value) return true; // Permitir vacío si no tiene vehículo
    
    const plate = vehiclePlate.value.trim();
    if (plate.length === 0) return true;
    
    // Regex: 1-2 letras iniciales + 3-6 números = 4-7 caracteres totales
    const plateRegex = /^[A-Z]{1,2}[0-9]{3,6}$/;
    return plateRegex.test(plate);
  });

  /**
   * Formatea y valida la placa mientras el usuario escribe
   * Solo permite letras mayúsculas y números
   */
  const formatVehiclePlate = (event) => {
    let value = event.target.value.toUpperCase();
    
    // Solo permitir letras y números
    value = value.replace(/[^A-Z0-9]/g, '');
    
    vehiclePlate.value = value;
    
    // Validar formato
    if (value.length > 0 && !isValidVehiclePlate.value) {
      vehiclePlateError.value = 'Formato inválido. Use 1-2 letras + 3-6 números (Ej: A12345)';
    } else {
      vehiclePlateError.value = '';
    }
  };

  // Inicializar datos del vehículo si ya existen
  onMounted(() => {
    if (props.visit.vehicle_plate) {
      hasVehicle.value = true;
      vehiclePlate.value = props.visit.vehicle_plate;
    }
    localError.value = '';
  });

  // Escuchar errores desde el padre
  watch(
    () => props.errorMessage,
    (newError) => {
      if (newError) {
        localError.value = newError;
      }
    },
    { immediate: true }
  );

  /**
   * Inicia el modo de edición de la placa de vehículo
   */
  const startEditingVehicle = () => {
    isEditingVehicle.value = true;
    hasVehicle.value = true;
  };

  /**
   * Cancela la edición y restaura el valor original
   */
  const cancelEditingVehicle = () => {
    isEditingVehicle.value = false;
    hasVehicle.value = true;
    vehiclePlate.value = props.visit.vehicle_plate;
  };

  /**
   * Guarda la placa de vehículo mediante PATCH al endpoint
   */
  const saveVehiclePlate = async () => {
    // Validar formato antes de enviar
    if (hasVehicle.value && vehiclePlate.value && !isValidVehiclePlate.value) {
      vehiclePlateError.value = 'Formato inválido. Use 1-2 letras + 3-6 números';
      return;
    }

    localError.value = '';
    vehiclePlateError.value = '';
    isSavingVehicle.value = true;

    try {
      const payload = {
        vehicle_plate: hasVehicle.value && vehiclePlate.value ? vehiclePlate.value.trim() : null
      };

      await api.patch(`/visits/${props.visit.id}/vehicle`, payload);
      
      // Actualizar el objeto visit localmente
      props.visit.vehicle_plate = payload.vehicle_plate;
      
      isEditingVehicle.value = false;
      
      // Emitir evento para que el padre actualice la lista
      emit('vehicleUpdated', props.visit);
      
    } catch (error) {
      logger.error('Error al actualizar placa', error);
      localError.value = error.response?.data?.message || 'Error al actualizar la placa del vehículo';
    } finally {
      isSavingVehicle.value = false;
    }
  };

  /**
   * Cierra la visita y registra placa si se especificó en modal de cierre
   */
  const closeVisit = () => {
    localError.value = '';
    // Emitir confirmación sin la placa (ya se gestiona en Ver Detalles)
    emit('confirm', {
      visit: props.visit,
      vehicle_plate: null // No enviar placa aquí, ya se guardó separadamente
    });
  };

  const getFullName = (visit) => {
    const visitor = visit.visitors?.[0] || {};
    return `${visitor.name ?? ''} ${visitor.lastName ?? ''}`.trim();
  };
  
  const getCarnetNumber = (visit) => {
    return visit.assigned_carnet ?? '—';
  };
  
  const getNum = (visit) => {
    const visitor = visit.visitors?.[0] || {};
    return visitor.phone ?? '';
  };

  const getMail = (visit) => {
    const visitor = visit.visitors?.[0] || {};
    return visitor.email ?? '';
  };

  const getInstitution = (visit) => {
    const visitor = visit.visitors?.[0] || {};
    return visitor.institution ?? '';
  };

  // Alias para mantener compatibilidad con template existente
  const getFormattedDocumentLabel = getFormattedDocumentUtil;
  
  // formatDate local que incluye hora
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
</script>

