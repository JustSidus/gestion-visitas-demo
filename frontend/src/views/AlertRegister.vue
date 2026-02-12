<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import AppLayout from '@/components/layouts/AppLayout.vue';
import AlertService from '@/services/AlertService';
import VisitService from '@/services/VisitService';
import Swal from 'sweetalert2';
import { Icon } from '@iconify/vue';
import logger from '@/utils/logger';

const router = useRouter();
const route = useRoute();

// =============================================================================
// ESTADOS PRINCIPALES
// =============================================================================

const visit = ref(null);
const visitor = ref(null);
const isLoadingVisit = ref(true);
const isSubmitting = ref(false);
const isInitialDataLoading = ref(false);
const isDataLoaded = ref(false);

// =============================================================================
// DATOS MAESTROS
// =============================================================================

const allOriginTypes = ref([]);
const allOriginCases = ref([]);
const allAlertTypes = ref([]);
const allProvinces = ref([]);
const allMunicipalities = ref([]);
const allInstitutions = ref([]);
const allSocialMedia = ref([]);
const allInstitutionalProtocols = ref([]);
const allEmployeePositions = ref([]);
const allGenders = ref([]);

// Por ahora usamos un valor por defecto en frontend; si queremos una lista centralizada,
// deberíamos moverla a un endpoint backend y cargarla desde aquí.
const offices = [
  { id: 1, label: "Sede Central" }
];

// =============================================================================
// FORMULARIO
// =============================================================================

const today = new Date();
const localDate = new Date(today.getTime() - today.getTimezoneOffset() * 60000)
    .toISOString()
    .slice(0, 10);

const formData = ref({
  visit_id: null,
  visitor_id: null,
  type_origin_case_id: '',
  origin_case_id: '',
  alert_type_id: '',
  receiver_departament: 'Sede Central',
  province_id: '',
  municipality_id: '',
  description: '',
  media_link: '',
  start_date: localDate,
  localition_description: '',
  alert_details_option_id: null,
  employee_position_id: null,
});

// Formatea el número de teléfono ingresado (000-000-0000)
// targetRef es opcional (objeto reactivo con la propiedad phone), por defecto usa visitor
const handlePhoneInput = (event, targetRef = visitor) => {
  let inputValue = event.target.value

  // Extraer solo dígitos
  inputValue = inputValue.replace(/[^0-9]/g, '')
  const limitedDigits = inputValue.substring(0, 10)

  // Aplicar formato con guiones de forma dinámica: 000-000-0000
  let formatted = ''
  for (let i = 0; i < limitedDigits.length; i++) {
    if (i === 3 || i === 6) {
      formatted += '-'
    }
    formatted += limitedDigits[i]
  }

  // Asignar al teléfono del target (ej: personWhoRegister.phone o visitor.phone)
  if (targetRef && typeof targetRef === 'object') {
    targetRef.phone = formatted
  }
}

const institutionRelated = ref({
  name: '',
  phone: '',
  relation_type: 'institucion',
  description: '',
  employee_position_id: null,
});

const citizenReporter = ref({
  name: '',
  phone: '',
  relation_type: 'reportero',
  description: '',
  employee_position_id: null,
});

const citizenRelated = ref({
  name: '',
  phone: '',
  relation_type: 'persona relacionada',
  description: '',
  employee_position_id: null,
});

const personWhoReceive = ref({
  name: '',
  phone: '',
  relation_type: 'recibio la alerta',
  description: '',
  employee_position_id: null,
});

const personWhoRegister = ref({
  name: '',
  phone: '',
  relation_type: 'persona que registra',
  description: '',
  employee_position_id: null,
});

const nnaList = ref([]);

const errors = ref({
  type_origin_case_id: false,
  origin_case_id: false,
  name: false,
  has_birth_certificate: false,
  ageMeasuredIn: false,
  gender_id: false,
});

// =============================================================================
// MODAL NNA
// =============================================================================

const addNnaModal = ref(false);
const nnaSuggestions = ref([]);
const isSearchingRelatedNNA = ref(false);
const isEditing = ref(false);
const searchTimeout = ref(null);
const alreadyExistsNNA = ref(false);

const nnaModalErrors = ref({
  unit_id: false,
  ageMeasuredIn: false,
});

const newNna = ref({
  id: null,
  code: null,
  name: '',
  lastname: '',
  gender_id: '',
  birth_date: '',
  age: '',
  ageMeasuredIn: '',
  ageCalculatedBy: '',
  has_birth_certificate: null,
});

// =============================================================================
// COMPUTED
// =============================================================================

const municipalityOptions = computed(() => {
  if (!formData.value.province_id) return [];
  return allMunicipalities.value.filter(
    (m) => m.province_id === formData.value.province_id
  );
});

const originCaseOptions = computed(() => {
  if (!formData.value.type_origin_case_id) return [];
  return allOriginCases.value.filter(
    (oc) => oc.type_origin_case_id === formData.value.type_origin_case_id
  );
});

const alertDetailOptions = computed(() => {
  switch (formData.value.alert_type_id) {
    case 3:
      return allInstitutions.value;
    case 4:
      return allSocialMedia.value;
    case 5:
      return allInstitutionalProtocols.value;
    default:
      return [];
  }
});

const isInitialLoading = computed(() => isInitialDataLoading.value);

const isFormValid = computed(() => {
  return (
    formData.value.type_origin_case_id &&
    formData.value.origin_case_id &&
    formData.value.description?.trim()
  );
});

const visitorFullName = computed(() => {
  if (!visitor.value) return '';
  return `${visitor.value.name || ''} ${visitor.value.lastName || ''}`.trim();
});

const messageOfNnaInModal = computed(() => {
  if (alreadyExistsNNA.value) {
    return "NNA seleccionado correctamente. Puedes proceder a agregarlo.";
  }
  
  if (!newNna.value.name && !newNna.value.lastname) {
    return "Al registrar valores buscaremos similitudes en el sistema";
  }
  
  if (isSearchingRelatedNNA.value === false && nnaSuggestions.value.length === 0) {
    return "No encontramos registros similares. Se creará un nuevo NNA con esta información.";
  }
  
  return "";
});

// =============================================================================
// WATCHERS
// =============================================================================

watch(() => formData.value.province_id, () => {
  formData.value.municipality_id = '';
});

watch(() => formData.value.type_origin_case_id, () => {
  formData.value.origin_case_id = '';
});

watch(() => newNna.value.name, (newValue) => {
  if (newValue) errors.value.name = false;
});

watch(() => newNna.value.has_birth_certificate, (newValue) => {
  if (newValue) errors.value.has_birth_certificate = false;
});

watch(() => newNna.value.ageMeasuredIn, (newValue) => {
  if (newValue) errors.value.ageMeasuredIn = false;
});

watch(() => formData.value.type_origin_case_id, (newValue) => {
  if (newValue) errors.value.type_origin_case_id = false;
});

watch(() => newNna.value.gender_id, (newValue) => {
  if (newValue) errors.value.gender_id = false;
});

// =============================================================================
// FUNCIONES
// =============================================================================

async function loadAllMasterData() {
  if (isDataLoaded.value) return;

  isInitialDataLoading.value = true;
  logger.log('Cargando datos maestros...');

  try {
    // Usar el endpoint consolidado optimizado (1 petición en lugar de 9+)
    const masterData = await AlertService.loadAllMasterDataConsolidated();
    
    allOriginTypes.value = masterData.originTypes;
    allAlertTypes.value = masterData.alertTypes;
    allProvinces.value = masterData.provinces;
    allMunicipalities.value = masterData.allMunicipalities;
    allInstitutions.value = masterData.institutions;
    allSocialMedia.value = masterData.socialMedia;
    allInstitutionalProtocols.value = masterData.institutionalProtocols;
    allEmployeePositions.value = masterData.employeePositions;
    allGenders.value = masterData.genders;
    allOriginCases.value = masterData.originCases;

    isDataLoaded.value = true;
    logger.success('Datos maestros cargados');
  } catch (error) {
    logger.error('Error cargando datos maestros:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudieron cargar los datos del formulario',
    });
  } finally {
    isInitialDataLoading.value = false;
  }
}

const searchNna = async () => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }

  searchTimeout.value = setTimeout(async () => {
    isSearchingRelatedNNA.value = true;
    alreadyExistsNNA.value = false;

    if (!newNna.value.name && !newNna.value.lastname) {
      nnaSuggestions.value = [];
      isSearchingRelatedNNA.value = false;
      return;
    }

    try {
      const response = await AlertService.searchNNA(
        newNna.value.name,
        newNna.value.lastname
      );
      nnaSuggestions.value = response;
    } catch (error) {
      logger.error('Error al buscar NNA', error);
    } finally {
      isSearchingRelatedNNA.value = false;
    }
  }, 500);
};

const selectNna = (nna) => {
  alreadyExistsNNA.value = true;

  newNna.value.id = nna.id;
  newNna.value.code = nna.code;
  newNna.value.name = nna.name;
  newNna.value.lastname = nna.surname;
  newNna.value.gender_id = nna.gender_id;
  
  if (nna.birth_date) {
    newNna.value.has_birth_certificate = 1;
    newNna.value.birth_date = nna.birth_date;
    newNna.value.age = '';
    newNna.value.ageMeasuredIn = '';
    newNna.value.ageCalculatedBy = '';
  } else if (nna.age) {
    newNna.value.has_birth_certificate = 2;
    newNna.value.age = nna.age;
    newNna.value.ageMeasuredIn = nna.ageMeasuredIn || nna.age_measured_in || '';
    newNna.value.ageCalculatedBy = nna.ageCalculatedBy || nna.age_unit_id || '';
    newNna.value.birth_date = '';
  } else {
    newNna.value.has_birth_certificate = 2;
  }
  
  nnaSuggestions.value = [];
  isSearchingRelatedNNA.value = false;
  
  errors.value = {
    name: false,
    has_birth_certificate: false,
    ageMeasuredIn: false,
    type_origin_case_id: false,
    origin_case_id: false,
    gender_id: false,
  };
  nnaModalErrors.value = {
    unit_id: false,
    ageMeasuredIn: false,
  };
  
  isEditing.value = true;
};

const addNna = () => {
  nnaModalErrors.value.unit_id = false;
  nnaModalErrors.value.ageMeasuredIn = false;

  if (newNna.value.has_birth_certificate === 2 && newNna.value.age) {
    if (!newNna.value.ageCalculatedBy) {
      nnaModalErrors.value.unit_id = true;
    }
    if (!newNna.value.ageMeasuredIn) {
      nnaModalErrors.value.ageMeasuredIn = true;
    }
    if (nnaModalErrors.value.unit_id || nnaModalErrors.value.ageMeasuredIn) {
      return;
    }
  }

  if (!newNna.value.name) {
    errors.value.name = true;
    return;
  }

  if (!newNna.value.has_birth_certificate) {
    errors.value.has_birth_certificate = true;
    return;
  }

  if (newNna.value.name) {
    nnaList.value.push({ ...newNna.value });
    closeAddNnaModal();
  }
};

const removeNna = (index) => {
  nnaList.value.splice(index, 1);
};

const closeAddNnaModal = () => {
  alreadyExistsNNA.value = false;
  nnaSuggestions.value = [];
  addNnaModal.value = false;
  newNna.value = {
    id: null,
    code: null,
    name: '',
    lastname: '',
    gender_id: '',
    birth_date: '',
    age: '',
    ageMeasuredIn: '',
    ageCalculatedBy: '',
    has_birth_certificate: null,
  };
  isEditing.value = false;
};

const handleInput = () => {
  isSearchingRelatedNNA.value = false;
  searchNna();
};

function validateForm() {
  let isValid = true;

  errors.value = {
    name: false,
    has_birth_certificate: false,
    ageMeasuredIn: false,
    type_origin_case_id: false,
    origin_case_id: false,
    gender_id: false,
  };

  if (!formData.value.type_origin_case_id) {
    errors.value.type_origin_case_id = true;
    isValid = false;
  }

  if (!formData.value.origin_case_id) {
    errors.value.origin_case_id = true;
    isValid = false;
  }

  return isValid;
}

const combineFromData = async () => {
  const alertData = { ...formData.value };
  const relatedEntities = [];

  if (personWhoRegister.value.name.trim()) {
    relatedEntities.push({ ...personWhoRegister.value });
  }

  switch (formData.value.alert_type_id) {
    case 2:
      if (citizenReporter.value.name.trim()) {
        relatedEntities.push({ ...citizenReporter.value });
      }
      if (citizenRelated.value.name.trim()) {
        relatedEntities.push({ ...citizenRelated.value });
      }
      break;
    case 3:
      if (institutionRelated.value.name.trim()) {
        relatedEntities.push({ ...institutionRelated.value });
      }
      break;
    case 4:
      if (citizenReporter.value.name.trim()) {
        relatedEntities.push({ ...citizenReporter.value });
      }
      if (citizenRelated.value.name.trim()) {
        relatedEntities.push({ ...citizenRelated.value });
      }
      break;
    case 5:
      if (personWhoReceive.value.name.trim()) {
        relatedEntities.push({ ...personWhoReceive.value });
      }
      break;
  }

  return {
    alert_detail: alertData,
    nna_list: nnaList.value,
    related_entities: relatedEntities,
  };
};

const handleSubmit = async () => {
  // Prevenir múltiples envíos
  if (isSubmitting.value) {
    return;
  }

  if (!validateForm()) {
    Swal.fire({
      icon: 'warning',
      title: 'Formulario Incompleto',
      text: 'Por favor complete todos los campos requeridos',
    });
    return;
  }

  isSubmitting.value = true;

  try {
    const result = await AlertService.registerAlert(await combineFromData());

    if (result.success) {
      await Swal.fire({
        icon: 'success',
        title: '¡Alerta Registrada!',
        text: `La alerta ha sido registrada exitosamente. ID del caso: ${result.data.case_id}`,
        allowOutsideClick: false,
        allowEscapeKey: false,
      });

      router.push('/mission-visits');
    } else {
      throw new Error(result.message || 'Error desconocido al registrar');
    }
  } catch (error) {
    logger.error('Error al registrar alerta', error);

    Swal.fire({
      icon: 'error',
      title: 'Error al Registrar',
      text: error.response?.data?.message || error.message || 'No se pudo registrar la alerta',
    });
  } finally {
    isSubmitting.value = false;
  }
};

const handleCancel = () => {
  // Prevenir cancelación durante el envío
  if (isSubmitting.value) {
    return;
  }

  Swal.fire({
    title: '¿Cancelar registro?',
    text: 'Se perderán todos los datos ingresados',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, cancelar',
    cancelButtonText: 'Continuar editando',
  }).then((result) => {
    if (result.isConfirmed) {
      router.push('/mission-visits');
    }
  });
};

// =============================================================================
// INICIALIZACIÓN
// =============================================================================

onMounted(async () => {
  const visitId = route.params.visitId;
  const visitorId = route.params.visitorId;

  if (!visitId || !visitorId) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se especificaron los parámetros necesarios',
    });
    router.push('/mission-visits');
    return;
  }

  // Mostrar loading inmediatamente
  isInitialDataLoading.value = true;

  try {
    const response = await VisitService.getById(visitId);
    
    if (!response || !response.data) {
      throw new Error('Respuesta inválida del servidor');
    }
    
    visit.value = response.data;
    visitor.value = visit.value.visitors?.find(v => v.id == visitorId);

    if (!visitor.value) {
      throw new Error('Visitante no encontrado en esta visita');
    }

    // Asignar IDs al formulario
    formData.value.visit_id = visitId;
    formData.value.visitor_id = visitorId;

    // Cargar datos maestros
    await loadAllMasterData();

  } catch (error) {
    logger.error('Error al cargar datos', error);
    
    let errorMessage = 'No se pudo cargar la información de la visita';
    
    if (error.response) {
      if (error.response.status === 403) {
        errorMessage = 'No tiene permisos para acceder a esta visita';
      } else if (error.response.status === 404) {
        errorMessage = 'La visita no existe';
      } else if (error.response.status === 500) {
        errorMessage = 'Error del servidor. Por favor, contacte al administrador';
      }
    } else if (error.request) {
      errorMessage = 'No se pudo conectar con el servidor';
    } else {
      errorMessage = error.message || errorMessage;
    }
    
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: errorMessage,
    });
    router.push('/mission-visits');
  } finally {
    isLoadingVisit.value = false;
  }
});

// Cleanup de timers al desmontar
onUnmounted(() => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = null;
  }
});
</script>

<template>
  <!-- Loading inicial - Muestra SOLO mientras se carga (fuera de AppLayout para evitar cortes) -->
  <div
    v-if="isInitialLoading"
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999] p-4"
  >
    <div class="bg-white rounded-lg p-8 text-center shadow-2xl max-w-sm w-full">
      <div class="h-12 w-12 animate-spin rounded-full border-4 border-demo-blue-100 border-t-demo-blue-600 mx-auto mb-4"></div>
      <h3 class="text-lg font-medium text-gray-900">
        Cargando datos del formulario...
      </h3>
      <p class="text-sm text-gray-500 mt-2">Por favor espere...</p>
    </div>
  </div>

  <AppLayout>
    <div class="max-w-7xl mx-auto space-y-8">
      <!-- Header -->
      <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-left">
          <h1 class="text-2xl font-bold text-gray-900">
            Registrar alerta
          </h1>
          <p class="text-sm text-gray-600 mt-1">
            Completa los campos obligatorios y describe la situación con claridad para facilitar el seguimiento.
          </p>
        </div>
        <nav>
          <ol class="flex items-center gap-2">
            <li>
              <button
                @click="router.push('/mission-visits')"
                class="font-medium hover:text-blue-600 transition-colors"
              >
                Visitas Misionales /
              </button>
            </li>
            <li class="font-medium text-blue-600">
              Creación de Alerta
            </li>
          </ol>
        </nav>
      </div>

      <!-- Contenido - Se muestra SOLO cuando los datos están listos -->
      <template v-if="!isInitialLoading && isDataLoaded && visit && visitor">
        <!-- Info de la Visita -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <div class="flex items-center gap-3">
            <Icon icon="mdi:information" class="text-3xl text-blue-600" />
            <div>
              <p class="font-semibold text-gray-800">Registrando alerta para:</p>
              <p class="text-sm text-gray-600">
                <span class="font-medium">Visitante:</span> {{ visitorFullName }} |
                <span class="font-medium">Visita ID:</span> {{ visit.id }}
              </p>
            </div>
          </div>
        </div>

        <!-- Formulario -->
        <form @submit.prevent="handleSubmit">
        <!-- Grid del formulario -->
        <div class="bg-white p-4 md:p-6 rounded-lg shadow-sm border border-gray-200">
          <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6">
            
            <!-- ========== INFORMACIÓN GENERAL ========== -->
            <div class="col-span-full">
              <h3 class="text-lg font-semibold text-blue-600 mb-4">
                Información General
              </h3>
            </div>

            <!-- Tipo de origen -->
            <div class="col-span-full lg:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de origen de esta denuncia
                <span class="text-red-500">*</span>
              </label>
              <select
                v-model="formData.type_origin_case_id"
                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="errors.type_origin_case_id ? 'border-red-500' : 'border-gray-300'"
              >
                <option value="">Seleccione...</option>
                <option v-for="type in allOriginTypes" :key="type.id" :value="type.id">
                  {{ type.name }}
                </option>
              </select>
              <p v-if="errors.type_origin_case_id" class="text-red-500 text-sm mt-1">
                Este campo es requerido
              </p>
            </div>

            <!-- Origen de la denuncia -->
            <div class="col-span-full lg:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Origen de esta denuncia
                <span class="text-red-500">*</span>
              </label>
              <select
                v-model="formData.origin_case_id"
                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="errors.origin_case_id ? 'border-red-500' : 'border-gray-300'"
                :disabled="!formData.type_origin_case_id"
              >
                <option value="">
                  {{ formData.type_origin_case_id ? 'Seleccione...' : 'Debe seleccionar un tipo de caso ...' }}
                </option>
                <option v-for="origin in originCaseOptions" :key="origin.id" :value="origin.id">
                  {{ origin.name }}
                </option>
              </select>
              <p v-if="errors.origin_case_id" class="text-red-500 text-sm mt-1">
                Este campo es requerido
              </p>
            </div>

            <!-- Fecha de recepción -->
            <div class="col-span-full lg:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Fecha de recepción de la alerta
              </label>
              <input
                type="date"
                v-model="formData.start_date"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Departamento Institución Demo (valor por defecto) -->
            <div class="col-span-full lg:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Departamento de la Institución Demo que recibe esta alerta
              </label>
              <!-- Mostrar el valor por defecto, no editable -->
              <input
                type="text"
                :value="formData.receiver_departament"
                readonly
                class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50 rounded-lg text-gray-700"
              />
              <!-- Input oculto para asegurar envío del valor al backend -->
              <input type="hidden" v-model="formData.receiver_departament" />
            </div>

            <!-- Funcionario que registra -->
            <div class="col-span-full grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Funcionario de la Institución Demo que registra la denuncia
                </label>
                <input
                  type="text"
                  v-model="personWhoRegister.name"
                  placeholder="Nombre de la persona que hace el registro"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Teléfono de la persona que registra
                </label>
                <input
                  type="tel"
                  v-model="personWhoRegister.phone"
                  @input="(e) => handlePhoneInput(e, personWhoRegister)"
                  placeholder="000-000-0000"
                  maxlength="12"
                  inputmode="numeric"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>

            <!-- ========== INFORMACIÓN GEOGRÁFICA ========== -->
            <div class="col-span-full mt-6">
              <h3 class="text-lg font-semibold text-blue-600 mb-4">
                Información geográfica
              </h3>
            </div>

            <!-- Provincia -->
            <div class="col-span-full lg:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Provincia correpondiente
              </label>
              <select
                v-model="formData.province_id"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Seleccione la provincia...</option>
                <option v-for="province in allProvinces" :key="province.id" :value="province.id">
                  {{ province.name }}
                </option>
              </select>
            </div>

            <!-- Municipio -->
            <div class="col-span-full lg:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Municipio correspondiente
              </label>
              <select
                v-model="formData.municipality_id"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :disabled="!formData.province_id"
              >
                <option value="">
                  {{ formData.province_id ? 'Seleccione el municipio...' : 'Debe selecionar la Provincia...' }}
                </option>
                <option v-for="municipality in municipalityOptions" :key="municipality.id" :value="municipality.id">
                  {{ municipality.name }}
                </option>
              </select>
            </div>

            <!-- Dirección de referencia -->
            <div class="col-span-full">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Direccion de referencia
              </label>
              <input
                type="text"
                v-model="formData.localition_description"
                placeholder="Detalle de referencia como 'al lado de, frente a, etc.'"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- ========== PERSONAS O INSTITUCIONES IDENTIFICADAS ========== -->
            <div class="col-span-full mt-6">
              <h3 class="text-lg font-semibold text-blue-600 mb-4">
                Personas o instituciones identificadas en la alerta
              </h3>
            </div>

            <!-- Quien hizo la alerta -->
            <div class="col-span-full lg:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Quien hizo la alerta
              </label>
              <select
                v-model="formData.alert_type_id"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Seleccione...</option>
                <option v-for="alertType in allAlertTypes" :key="alertType.id" :value="alertType.id">
                  {{ alertType.name }}
                </option>
              </select>
            </div>

            <!-- Campos dinámicos según tipo de alerta: Ciudadano (2) -->
            <template v-if="formData.alert_type_id === 2">
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Nombre del ciudadano que hizo la alerta
                </label>
                <input
                  type="text"
                  v-model="citizenReporter.name"
                  placeholder="Ingrese el nombre"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Teléfono del ciudadano que hizo la alerta
                </label>
                <input
                  type="tel"
                  v-model="citizenReporter.phone"
                  @input="(e) => handlePhoneInput(e, citizenReporter)"
                  placeholder="000-000-0000"
                  maxlength="12"
                  inputmode="numeric"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  En caso de contar con información de otra persona relacionada, coloquela aquí
                </label>
                <input
                  type="text"
                  v-model="citizenRelated.name"
                  placeholder="Ingrese el nombre de la persona con información relevante"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Teléfono de otra persona con información relevante
                </label>
                <input
                  type="tel"
                  v-model="citizenRelated.phone"
                  @input="(e) => handlePhoneInput(e, citizenRelated)"
                  placeholder="000-000-0000"
                  maxlength="12"
                  inputmode="numeric"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </template>

            <!-- Campos dinámicos: Institución (3) -->
            <template v-if="formData.alert_type_id === 3">
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Selecciona institución o instancia que hizo la alerta
                </label>
                <select
                  v-model="formData.alert_details_option_id"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="">Seleccione...</option>
                  <option v-for="inst in alertDetailOptions" :key="inst.id" :value="inst.id">
                    {{ inst.name }}
                  </option>
                </select>
              </div>
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Nombre la persona de contacto de la institución o instancia
                </label>
                <input
                  type="text"
                  v-model="institutionRelated.name"
                  placeholder="Ingrese el nombre"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div class="col-span-full">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Teléfono la persona de contacto de la institución o instancia
                </label>
                <input
                  type="tel"
                  v-model="institutionRelated.phone"
                  @input="(e) => handlePhoneInput(e, institutionRelated)"
                  placeholder="000-000-0000"
                  maxlength="12"
                  inputmode="numeric"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </template>

            <!-- Campos dinámicos: Redes sociales (4) -->
            <template v-if="formData.alert_type_id === 4">
              <div class="col-span-full">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Link de la publicación reportada
                </label>
                <input
                  type="url"
                  v-model="formData.media_link"
                  placeholder="Ingrese el link de la alerta"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Identificar el medio de comunicación o red social que hizo la alerta
                </label>
                <select
                  v-model="formData.alert_details_option_id"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="">Seleccione...</option>
                  <option v-for="media in alertDetailOptions" :key="media.id" :value="media.id">
                    {{ media.name }}
                  </option>
                </select>
              </div>
            </template>

            <!-- Campos dinámicos: Institución Demo (5) -->
            <template v-if="formData.alert_type_id === 5">
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  El caso identificado por la Institución Demo:
                </label>
                <select
                  v-model="formData.alert_details_option_id"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="">Seleccione...</option>
                  <option v-for="protocol in alertDetailOptions" :key="protocol.id" :value="protocol.id">
                    {{ protocol.name }}
                  </option>
                </select>
              </div>
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Nombre del personal de la Institución Demo
                </label>
                <input
                  type="text"
                  v-model="personWhoReceive.name"
                  placeholder="Ingrese el nombre"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Cargo o posición
                </label>
                <select
                  v-model="formData.employee_position_id"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="">Seleccione...</option>
                  <option v-for="position in allEmployeePositions" :key="position.id" :value="position.id">
                    {{ position.name }}
                  </option>
                </select>
              </div>
              <div class="col-span-full lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Teléfono del personal de la Institución Demo
                </label>
                <input
                  type="tel"
                  v-model="personWhoReceive.phone"
                  @input="(e) => handlePhoneInput(e, personWhoReceive)"
                  placeholder="000-000-0000"
                  maxlength="12"
                  inputmode="numeric"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </template>

            <!-- ========== INFORMACIÓN DE LA SITUACIÓN ========== -->
            <div class="col-span-full mt-6">
              <h3 class="text-lg font-semibold text-blue-600 mb-4">
                Información de la situación y los NNA identificados
              </h3>
            </div>

            <div class="col-span-full">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Describa la situación
              </label>
              <textarea
                v-model="formData.description"
                placeholder="Descripción detallada de la situación..."
                rows="4"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
              ></textarea>
            </div>

            <!-- Sección NNA -->
            <div class="col-span-full p-4 bg-white rounded-lg shadow-sm border border-gray-200 mb-5">
              <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h3 class="text-lg font-medium text-gray-800">NNA</h3>
                <button
                  type="button"
                  @click="addNnaModal = true"
                  class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center select-none cursor-pointer transition-colors"
                >
                  <Icon icon="mdi:plus" class="text-xl mr-1" />
                  Agregar NNA
                </button>
              </div>

              <div class="mt-4">
                <div
                  v-if="nnaList.length === 0"
                  class="bg-gray-50 p-6 rounded-lg text-gray-500 text-center"
                >
                  <Icon icon="mdi:information-outline" class="text-4xl text-gray-400 mb-3 mx-auto" />
                  <p class="text-sm">
                    Aquí se listarán los NNA identificados en caso de contar con ellos
                  </p>
                </div>

                <div v-else class="mt-4">
                  <p class="text-sm text-gray-600 mb-3">
                    NNA identificados: {{ nnaList.length }}
                  </p>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                      v-for="(nna, index) in nnaList"
                      :key="index"
                      class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors"
                    >
                      <div class="flex items-center min-w-0">
                        <div class="h-8 w-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-3 flex-shrink-0 font-semibold">
                          {{ nna.name?.charAt(0) }}{{ nna.lastname?.charAt(0) }}
                        </div>
                        <span class="font-medium truncate">
                          {{ nna.name }} {{ nna.lastname }}
                        </span>
                      </div>
                      <button
                        type="button"
                        @click="removeNna(index)"
                        class="text-red-500 hover:text-red-700 p-1 rounded-full hover:bg-red-50 transition-colors flex-shrink-0 ml-2"
                        title="Eliminar"
                      >
                        <Icon icon="mdi:close" class="text-xl" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Botones de acción -->
            <div class="col-span-full flex flex-col sm:flex-row gap-3 justify-end">
              <button
                type="button"
                class="w-full sm:w-auto px-8 py-3 rounded-lg bg-red-600 text-white font-medium hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                @click="handleCancel"
                :disabled="isSubmitting"
              >
                Cancelar
              </button>
              <button
                type="submit"
                class="w-full sm:w-auto px-8 py-3 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                :disabled="!isFormValid || isSubmitting"
              >
                <Icon 
                  v-if="isSubmitting" 
                  icon="mdi:loading" 
                  class="text-xl animate-spin" 
                />
                <span>{{ isSubmitting ? 'Guardando...' : 'Guardar' }}</span>
              </button>
            </div>
          </div>
        </div>
        </form>
      </template>
    </div>
  </AppLayout>

  <!-- Modal NNA -->
  <transition name="modal">
    <div
      v-if="addNnaModal"
      class="fixed inset-0 bg-black/60 flex justify-center items-center z-[99999] p-4"
    >
      <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Header -->
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-white sticky top-0 z-10">
          <h3 class="text-lg font-medium text-black">Agregar NNA</h3>
          <button
            @click="closeAddNnaModal"
            class="text-red-500 font-bold hover:text-red-700 transition-colors"
          >
            Cerrar
          </button>
        </div>

        <!-- Contenido -->
        <div class="flex-1 overflow-y-auto p-4 md:p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Nombre -->
            <div class="md:col-span-2 lg:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Nombre <span class="text-red-500">*</span>
              </label>
              <input
                type="text"
                v-model="newNna.name"
                @input="handleInput"
                placeholder="Nombre del NNA"
                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="errors.name ? 'border-red-500' : 'border-gray-300'"
              />
              <p v-if="errors.name" class="text-red-500 text-sm mt-1">
                Este campo es requerido
              </p>
            </div>

            <!-- Apellido -->
            <div class="md:col-span-2 lg:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Apellido
              </label>
              <input
                type="text"
                v-model="newNna.lastname"
                @input="handleInput"
                placeholder="Apellido del NNA"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Sexo -->
            <div class="md:col-span-2 lg:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Sexo
              </label>
              <select
                v-model="newNna.gender_id"
                @change="handleInput"
                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="errors.gender_id ? 'border-red-500' : 'border-gray-300'"
              >
                <option value="">Sexo del NNA</option>
                <option v-for="gender in allGenders" :key="gender.id" :value="gender.id">
                  {{ gender.name }}
                </option>
              </select>
            </div>

            <!-- Acta de nacimiento -->
            <div class="md:col-span-2 lg:col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                ¿Cuenta con acta de nacimiento? <span class="text-red-500">*</span>
              </label>
              <select
                v-model="newNna.has_birth_certificate"
                @change="handleInput"
                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="errors.has_birth_certificate ? 'border-red-500' : 'border-gray-300'"
              >
                <option :value="null">Seleccione...</option>
                <option :value="1">Si</option>
                <option :value="2">No</option>
              </select>
              <p v-if="errors.has_birth_certificate" class="text-red-500 text-sm mt-1">
                Este campo es requerido
              </p>
            </div>

            <!-- Si tiene acta: Fecha de nacimiento -->
            <template v-if="newNna.has_birth_certificate === 1">
              <div class="md:col-span-2 lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Fecha de nacimiento
                </label>
                <input
                  type="date"
                  v-model="newNna.birth_date"
                  @input="handleInput"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </template>

            <!-- Si NO tiene acta: Edad, Unidad, Calculada por -->
            <template v-else-if="newNna.has_birth_certificate === 2">
              <div class="md:col-span-2 lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Edad
                </label>
                <input
                  type="number"
                  v-model="newNna.age"
                  @input="handleInput"
                  placeholder="Edad del NNA"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div class="md:col-span-2 lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Unidad
                </label>
                <select
                  v-model="newNna.ageMeasuredIn"
                  @change="handleInput"
                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="">Seleccione...</option>
                  <option value="1">Meses</option>
                  <option value="2">Años</option>
                </select>
                <p v-if="nnaModalErrors.unit_id" class="text-red-500 text-sm mt-1">
                  La unidad es obligatoria si se ingresa la edad.
                </p>
              </div>
              <div class="md:col-span-2 lg:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Edad calculada por
                </label>
                <select
                  v-model="newNna.ageCalculatedBy"
                  @change="handleInput"
                  class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  :class="errors.ageMeasuredIn ? 'border-red-500' : 'border-gray-300'"
                >
                  <option value="">Seleccione...</option>
                  <option value="1">Estimación</option>
                  <option value="2">Evaluación ósea</option>
                </select>
                <p v-if="nnaModalErrors.ageMeasuredIn" class="text-red-500 text-sm mt-1">
                  Este campo es obligatorio si se ingresa la edad.
                </p>
              </div>
            </template>
          </div>

          <!-- Loading búsqueda -->
          <div v-if="isSearchingRelatedNNA" class="mt-6 text-center">
            <Icon icon="mdi:loading" class="text-4xl text-blue-600 animate-spin mx-auto" />
            <p class="text-gray-600 mt-2">Buscando coincidencias...</p>
          </div>

          <!-- Sugerencias NNA -->
          <div
            v-else-if="nnaSuggestions.length"
            class="border border-blue-300 rounded-lg overflow-hidden mt-6"
          >
            <div class="bg-blue-100 p-4 border-b border-blue-300">
              <p class="text-blue-800 font-medium flex items-center">
                <Icon icon="mdi:information" class="text-xl mr-2" />
                Encontramos registros similares. ¿Deseas agregar uno existente?
              </p>
            </div>
            <div class="max-h-60 overflow-y-auto">
              <div
                v-for="nna in nnaSuggestions"
                :key="nna.id"
                @click="selectNna(nna)"
                class="p-4 hover:bg-gray-50 cursor-pointer border-b border-gray-200 transition-colors"
              >
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                  <div class="flex-1">
                    <p class="font-medium text-gray-900 text-base">
                      {{ nna.name }} {{ nna.surname }}
                    </p>
                    <p class="text-sm text-gray-600 mt-1" v-if="nna.nickname">
                      Apodo: {{ nna.nickname }}
                    </p>
                    
                    <div class="flex flex-wrap gap-2 mt-2">
                      <span 
                        v-if="nna.code" 
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                      >
                        <Icon icon="mdi:id-card" class="mr-1" />
                        {{ nna.code }}
                      </span>
                      
                      <span 
                        v-if="nna.age" 
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                      >
                        <Icon icon="mdi:calendar" class="mr-1" />
                        {{ nna.age }} {{ (nna.age_measured_in == '1' || nna.ageMeasuredIn == '1' || nna.age_measured_in == 1 || nna.ageMeasuredIn == 1) ? 'meses' : 'años' }}
                      </span>
                      
                      <span 
                        v-if="nna.gender_id" 
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="nna.gender_id === 1 ? 'bg-blue-50 text-blue-700' : 'bg-pink-50 text-pink-700'"
                      >
                        <Icon :icon="nna.gender_id === 1 ? 'mdi:gender-male' : 'mdi:gender-female'" class="mr-1" />
                        {{ nna.gender_id === 1 ? 'Masculino' : 'Femenino' }}
                      </span>
                    </div>
                  </div>
                  
                  <button class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded text-sm font-medium transition-colors whitespace-nowrap">
                    Seleccionar
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Mensaje -->
          <div
            v-else-if="isSearchingRelatedNNA === false && nnaSuggestions.length === 0"
            class="p-4 bg-gray-100 rounded-lg mt-6"
          >
            <p class="text-gray-700 text-center">
              {{ messageOfNnaInModal }}
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-gray-200 p-4 bg-white sticky bottom-0">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button
              @click="closeAddNnaModal"
              class="bg-red-600 text-white py-3 px-4 rounded-lg hover:bg-red-700 transition-colors"
            >
              Cerrar
            </button>
            <button
              @click="addNna"
              class="py-3 px-4 rounded-lg transition-colors"
              :class="isSearchingRelatedNNA ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 text-white hover:bg-blue-700'"
              :disabled="isSearchingRelatedNNA"
            >
              {{ isEditing ? "Seleccionar" : "Agregar" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
