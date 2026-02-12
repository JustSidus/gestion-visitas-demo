<template>
  <AppLayout :stats="stats">
    <div class="max-w-7xl mx-auto space-y-8">
      <!-- Header -->
      <div class="text-left">
        <h1 class="text-2xl font-bold text-gray-900">
          Registro de Nueva Visita
        </h1>
        <p class="text-sm text-gray-600 mt-1">Complete la información del visitante y los detalles de la visita</p>
      </div>

      <!-- Step 1: Document Search -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-8 py-6 border-b border-blue-100">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
              <Icon icon="mdi:account-search" class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <h2 class="text-xl font-semibold text-gray-900">Búsqueda de Visitante</h2>
              <p class="text-sm text-gray-600">Ingrese el documento de identidad para buscar o crear un visitante</p>
            </div>
          </div>
        </div>

        <div class="p-8">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Document Type -->
            <div class="space-y-2">
              <label class="text-sm font-semibold text-gray-700">Tipo de Documento</label>
              <AppDropdown
                v-model="documentType"
                @update:model-value="clearIdentityDocument"
                icon="mdi:card-account-details"
                placeholder="Seleccione tipo de documento"
                :options="documentTypeOptions"
              />
            </div>

            <!-- Document Number - Hidden if type 3 -->
            <div v-if="Number(documentType) !== 3" class="space-y-2">
              <label class="text-sm font-semibold text-gray-700">Número de Documento</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <Icon icon="mdi:identifier" class="w-5 h-5 text-gray-400" />
                </div>
                <input 
                  type="text"
                  v-model="identityDocument"
                  @input="handleIdentityInput"
                  @keydown.enter="isSearchEnabled && searchVisitor()"
                  :disabled="!documentType"
                  :placeholder="Number(documentType) === 1 ? '000-0000000-0' : 'A12345678'"
                  :maxlength="Number(documentType) === 1 ? 13 : 9"
                  :inputmode="Number(documentType) === 1 ? 'numeric' : 'text'"
                  class="block w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm disabled:bg-gray-50"
                />
              </div>
              <p v-if="documentError" class="text-sm text-red-600 flex items-center gap-2">
                <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                {{ documentError }}
              </p>
            </div>

            <!-- Search Button - Hidden if type 3 -->
            <div v-if="Number(documentType) !== 3" class="space-y-2">
              <label class="text-sm font-semibold text-gray-700">&nbsp;</label>
              <button 
                @click="searchVisitor"
                :disabled="isSearchingVisitor || !isSearchEnabled"
                class="w-full h-12 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:from-gray-300 disabled:to-gray-400 text-white font-semibold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl disabled:shadow-none"
              >
                <Icon v-if="isSearchingVisitor" icon="mdi:loading" class="w-5 h-5 animate-spin" />
                <Icon v-else icon="mdi:magnify" class="w-5 h-5" />
                {{ isSearchingVisitor ? 'Buscando...' : 'Buscar Visitante' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State - Hidden if type 3 -->
      <div v-if="!showForm && Number(documentType) !== 3" class="bg-white rounded-2xl border-2 border-dashed border-gray-300 p-12">
        <div class="text-center">
          <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
            <Icon icon="mdi:account-plus" class="w-12 h-12 text-gray-400" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Complete la búsqueda</h3>
          <p class="text-gray-500 max-w-md mx-auto">
            Ingrese el número de documento del visitante y presione "Buscar Visitante" para continuar con el registro
          </p>
        </div>
      </div>
<!-- .. -->
      <!-- Registration Form -->
      <form v-if="showForm" @submit.prevent="saveVisit" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Visitor Information -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-green-100">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                <Icon icon="mdi:account" class="w-5 h-5 text-green-600" />
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Datos del Visitante</h3>
                <p class="text-sm text-gray-600">Información personal del visitante</p>
              </div>
            </div>
          </div>

          <div class="p-6 space-y-6">
            <!-- Document (Read Only) - Hidden if type 3 -->
            <div v-if="Number(documentType) !== 3" class="space-y-2">
              <label class="text-sm font-medium text-gray-700">Documento de Identidad</label>
              <input 
                type="text"
                v-model="visitor.identity_document"
                disabled
                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm"
              />
            </div>

            <!-- Name -->
            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700">
                Nombre <span class="text-red-500">*</span>
              </label>
              <input 
                type="text"
                v-model="visitor.name"
                @input="clearFieldError('name')"
                placeholder="Nombre del visitante"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
                :class="errors.name ? 'border-red-300 bg-red-50' : ''"
              />
              <p v-if="errors.name" class="text-sm text-red-600 flex items-center gap-2">
                <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                {{ errors.name }}
              </p>
            </div>

            <!-- Last Name -->
            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700">
                Apellido <span class="text-red-500">*</span>
              </label>
              <input 
                type="text"
                v-model="visitor.lastName"
                @input="clearFieldError('lastName')"
                placeholder="Apellido del visitante"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
                :class="errors.lastName ? 'border-red-300 bg-red-50' : ''"
              />
              <p v-if="errors.lastName" class="text-sm text-red-600 flex items-center gap-2">
                <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                {{ errors.lastName }}
              </p>
            </div>

            <!-- Institucion a la que pertenece -->
            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700">Institución a la que pertenece (opcional)</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <Icon icon="mdi:office-building-outline" class="w-5 h-5 text-gray-400" />
                </div>
                <input 
                  type="text"
                  v-model="visitor.institution"
                  placeholder="Ej: Claro, Banco Popular, etc."
                  class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
                />
              </div>
            </div>

            <!-- Phone -->
            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700">Teléfono (opcional)</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <Icon icon="mdi:phone" class="w-5 h-5 text-gray-400" />
                </div>
                <input 
                  type="text"
                  v-model="visitor.phone"
                  @input="handlePhoneInput"
                  placeholder="809-000-0000"
                  maxlength="12"
                  inputmode="numeric"
                  class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
                  :class="errors.phone ? 'border-red-300 bg-red-50' : ''"
                />
              </div>
              <p v-if="errors.phone" class="text-sm text-red-600 flex items-center gap-2">
                <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                {{ errors.phone }}
              </p>
            </div>

            <!-- Email -->
            <div class="space-y-2">
              <label class="text-sm font-medium text-gray-700">Correo Electrónico (opcional)</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <Icon icon="mdi:email" class="w-5 h-5 text-gray-400" />
                </div>
                <input 
                  type="email"
                  v-model="visitor.email"
                  placeholder="correo@ejemplo.com"
                  class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
                  :class="errors.email ? 'border-red-300 bg-red-50' : ''"
                />
              </div>
              <p v-if="errors.email" class="text-sm text-red-600 flex items-center gap-2">
                <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                {{ errors.email }}
              </p>
            </div>
          </div>
        </div>

        <!-- Visit Information -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-gradient-to-r from-blue-50 to-purple-50 px-6 py-4 border-b border-blue-100">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                <Icon icon="mdi:clipboard-text" class="w-5 h-5 text-blue-600" />
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Datos de la Visita</h3>
                <p class="text-sm text-gray-600">Información sobre la visita a realizar</p>
              </div>
            </div>
          </div>

          <div class="p-6 space-y-6">
            <!-- Mission Case Checkbox -->
            <div class=" rounded-xl p-4">
              <div class="flex items-start gap-3">
                <input 
                  id="missionCase"
                  type="checkbox"
                  v-model="visit.mission_case"
                  class="mt-1 w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                />
                <div>
                  <label for="missionCase" class="text-sm font-semibold text-gray-900 cursor-pointer">
                    Caso Misional
                  </label>
                  <p class="text-xs text-gray-600 mt-1">
                    Marque esta casilla si la visita es dirigida a la Unidad de Gestión de Casos
                  </p>
                </div>
              </div>
            </div>

            <!-- Person to Visit - Hidden if mission case -->
            <div v-if="!visit.mission_case" class="space-y-2">
              <label class="text-sm font-medium text-gray-700">
                Persona a Visitar <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <Icon icon="mdi:account-search" class="w-5 h-5 text-gray-400" />
                </div>
                <input 
                  type="text"
                  v-model="visit.namePersonToVisit"
                  @input="handlePersonToVisitInput"
                  @focus="handleFocus"
                  @blur="handleBlur"
                  placeholder="Escriba el nombre para buscar..."
                  autocomplete="off"
                  class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
                  :class="errors.namePersonToVisit ? 'border-red-300 bg-red-50' : ''"
                />
                
                <!-- Loading Spinner -->
                <div v-if="isSearchingUsers" class="absolute right-4 top-1/2 -translate-y-1/2">
                  <div class="w-5 h-5 border-2 border-purple-200 border-t-blue-600 rounded-full animate-spin"></div>
                </div>

                <!-- User Suggestions -->
                <div 
                  v-if="showUserSuggestions && userSearchResults.length > 0"
                  @mousedown.prevent
                  class="absolute z-10 w-full mt-2 bg-white border border-gray-200 rounded-xl shadow-xl max-h-60 overflow-y-auto"
                >
                  <div class="px-4 py-3 bg-gray-50 border-b text-xs text-gray-600 font-medium rounded-t-xl">
                    {{ userSearchResults.length }} usuario(s) encontrado(s)
                  </div>
                  <button
                    v-for="user in userSearchResults"
                    :key="user.id"
                    type="button"
                    @click="selectUser(user)"
                    class="w-full text-left px-4 py-3 hover:bg-purple-50 focus:bg-purple-50 border-b border-gray-100 last:border-b-0 transition-colors"
                  >
                    <div class="font-medium text-gray-900">{{ user.displayName }}</div>
                    <div class="text-sm text-gray-600">{{ user.mail || user.userPrincipalName }}</div>
                    <div v-if="user.jobTitle" class="text-xs text-gray-500">{{ user.jobTitle }}</div>
                    <!-- <div v-if="user.department" class="text-xs text-blue-600">{{ user.department }}</div> -->
                  </button>
                </div>
              </div>
              
              <p v-if="errors.namePersonToVisit" class="text-sm text-red-600 flex items-center gap-2">
                <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                {{ errors.namePersonToVisit }}
              </p>
              <p class="text-xs text-gray-500 ml-auto">Escriba al menos 3 caracteres para buscar</p>

              <!-- Email Notification -->
              <div v-if="visit.person_to_visit_email" class="bg-blue-50 border border-blue-200 rounded-xl p-4 space-y-3">
                <div class="flex items-center gap-2 text-sm text-blue-800">
                  <Icon icon="mdi:email" class="w-4 h-4" />
                  <span class="font-medium">{{ visit.person_to_visit_email }}</span>
                </div>
                <div class="flex items-center">
                  <input 
                    id="sendEmail"
                    type="checkbox"
                    v-model="visit.send_email"
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                  />
                  <label for="sendEmail" class="ml-2 text-sm text-gray-700 cursor-pointer font-medium">
                    Enviar notificación por correo electrónico
                  </label>
                </div>
                <p class="text-xs text-gray-600">Se notificará a esta persona cuando se registre la visita</p>
              </div>
            </div>

            <!-- Department - Hidden if mission case -->
            <div v-if="!visit.mission_case" class="space-y-2">
              <label class="text-sm font-medium text-gray-700">
                Departamento <span class="text-red-500">*</span>
              </label>
              <AppDropdown
                v-model="visit.department"
                @update:model-value="clearFieldError('department')"
                icon="mdi:office-building"
                placeholder="Seleccione un departamento"
                :error="!!errors.department"
                :options="departmentOptions"
                :searchable="true"
              />
              <p v-if="errors.department" class="text-sm text-red-600 flex items-center gap-2">
                <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                {{ errors.department }}
              </p>
            </div>

            <!-- Edificio - Hidden if mission case -->
            <div v-if="!visit.mission_case" class="space-y-2">
              <label class="text-sm font-medium text-gray-700">Edificio <span class="text-red-500">*</span></label>
              <AppDropdown
                v-model="visit.building"
                @update:model-value="clearFieldError('building')"
                icon="mdi:building"
                placeholder="Seleccione un edificio"
                :options="buildingOptions"
                :error="!!errors.building"
              />
              <p v-if="errors.building" class="text-sm text-red-600 flex items-center gap-2">
                <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                {{ errors.building }}
              </p>
            </div>

            <!-- Piso - Hidden if mission case -->
            <div v-if="!visit.mission_case" class="space-y-2">
              <label class="text-sm font-medium text-gray-700">Piso <span class="text-red-500">*</span></label>
              <AppDropdown
                v-model="visit.floor"
                @update:model-value="clearFieldError('floor')"
                icon="mdi:stairs"
                placeholder="Seleccione un piso"
                :options="floorOptions"
                :error="!!errors.floor"
              />
              <p v-if="errors.floor" class="text-sm text-red-600 flex items-center gap-2">
                <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                {{ errors.floor }}
              </p>
            </div>

            <!-- Reason - Hidden if mission case -->
            <div v-if="!visit.mission_case" class="space-y-2">
              <label class="text-sm font-medium text-gray-700">
                Motivo de la Visita <span class="text-red-500">*</span>
              </label>
              <textarea 
                v-model="visit.reason"
                @input="clearFieldError('reason')"
                placeholder="Describa el motivo de la visita"
                maxlength="500"
                rows="4"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm resize-none"
                :class="errors.reason ? 'border-red-300 bg-red-50' : ''"
              ></textarea>
              <div class="flex justify-between items-center">
                <p v-if="errors.reason" class="text-sm text-red-600 flex items-center gap-2">
                  <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                  {{ errors.reason }}
                </p>
                <p class="text-xs text-gray-500 ml-auto">{{ visit.reason.length }}/500 caracteres</p>
              </div>
            </div>

            <!-- Assigned Badge - Hidden if mission case -->
            <div v-if="!visit.mission_case" class="space-y-2">
              <label class="text-sm font-medium text-gray-700">
                Número de Carnet Asignado <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <Icon icon="mdi:badge-account" class="w-5 h-5 text-gray-400" />
                </div>
                <input 
                  type="number"
                  v-model="visit.assigned_carnet"
                  @input="clearFieldError('assigned_carnet')"
                  placeholder="Ingrese el número del carnet (1-99)"
                  min="1"
                  max="99"
                  class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm"
                  :class="errors.assigned_carnet ? 'border-red-300 bg-red-50' : ''"
                />
              </div>
              <p v-if="errors.assigned_carnet" class="text-sm text-red-600 flex items-center gap-2">
                <Icon icon="mdi:alert-circle" class="w-4 h-4" />
                {{ errors.assigned_carnet }}
              </p>
              <p class="text-xs text-gray-500">El número debe estar entre 1 y 99</p>
            </div>

            <!-- Current Date Time -->
            <div class="bg-gradient-to-r from-slate-50 to-gray-50 border border-slate-200 rounded-xl p-4">
              <label class="text-sm font-medium text-gray-700 mb-3 block">Fecha y Hora de Registro</label>
              <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <Icon icon="mdi:calendar" class="w-5 h-5 text-blue-600" />
                  </div>
                  <div>
                    <p class="text-xs text-gray-500">Fecha</p>
                    <p class="text-sm font-medium text-gray-900">{{ currentDateTime.date }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <Icon icon="mdi:clock" class="w-5 h-5 text-green-600" />
                  </div>
                  <div>
                    <p class="text-xs text-gray-500">Hora</p>
                    <p class="text-lg font-bold text-gray-900">{{ currentDateTime.time }}</p>
                  </div>
                </div>
              </div>
              <p class="mt-3 text-xs text-gray-500 flex items-center gap-2">
                <Icon icon="mdi:information" class="w-4 h-4" />
                La visita se registrará con la fecha y hora exacta al momento de guardar
              </p>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="lg:col-span-2 flex justify-end gap-4 pt-6">
          <button 
            type="button"
            @click="cancelForm"
            class="px-8 py-3 border border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-all duration-200 flex items-center gap-2"
          >
            <Icon icon="mdi:close" class="w-5 h-5" />
            Cancelar
          </button>
          <button 
            type="submit"
            :disabled="isSavingVisit"
            class="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:from-gray-400 disabled:to-gray-500 text-white font-semibold rounded-xl transition-all duration-200 flex items-center gap-2 shadow-lg hover:shadow-xl disabled:shadow-none"
          >
            <Icon v-if="isSavingVisit" icon="mdi:loading" class="w-5 h-5 animate-spin" />
            <Icon v-else icon="mdi:content-save" class="w-5 h-5" />
            {{ isSavingVisit ? 'Guardando...' : 'Guardar Visita' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Success Modal - Diseño Institucional Demo -->
    <Transition name="modal-fade">
      <div 
        v-if="showSuccessModal" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        @click="handleBackdropClick"
      >
        <Transition name="modal-scale">
          <div 
            v-if="showSuccessModal"
            class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden"
            @click.stop
          >
            <div class="p-8 text-center">
              <!-- Success Icon con gradiente institucional -->
              <div class="mb-6">
                <div class="w-24 h-24 bg-gradient-to-br from-demo-green-400 to-demo-green-600 rounded-full flex items-center justify-center mx-auto animate-scale-in shadow-lg">
                  <Icon icon="mdi:check-bold" class="w-14 h-14 text-white" />
                </div>
              </div>

              <!-- Title -->
              <h3 class="text-2xl font-bold text-gray-900 mb-3">
                ¡Visita Registrada!
              </h3>

              <!-- Message -->
              <p class="text-gray-600 mb-8 leading-relaxed">
                La visita ha sido creada exitosamente en el sistema.
              </p>

              <!-- OK Button con estilo institucional -->
              <button
                @click="handleOkClick"
                class="w-full px-6 py-3.5 bg-gradient-to-r from-demo-blue-600 to-demo-blue-700 hover:from-demo-blue-700 hover:to-demo-blue-800 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl hover:scale-105"
              >
                Continuar
              </button>
            </div>

            <!-- Progress Bar at Bottom -->
            <div class="h-1.5 bg-gray-100">
              <div 
                class="h-full bg-gradient-to-r from-demo-green-500 to-demo-green-600 transition-all ease-linear duration-100"
                :style="{ width: `${autoCloseProgress}%` }"
              ></div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Error Modal -->
    <Transition name="modal-fade">
      <div 
        v-if="showErrorModal" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50"
        @click="closeErrorModal"
      >
        <Transition name="modal-scale">
          <div 
            v-if="showErrorModal"
            class="bg-white rounded-lg shadow-xl max-w-md w-full p-8 text-center"
            @click.stop
          >
            <!-- Error Icon -->
            <div class="mb-4">
              <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto animate-scale-in">
                <Icon icon="mdi:close-circle" class="w-12 h-12 text-red-500" />
              </div>
            </div>

            <!-- Title -->
            <h3 class="text-xl font-semibold text-gray-900 mb-2">
              {{ errorTitle }}
            </h3>

            <!-- Message -->
            <p class="text-gray-600 mb-6 whitespace-pre-line">
              {{ errorMessage }}
            </p>

            <!-- OK Button -->
            <button
              @click="closeErrorModal"
              class="w-full px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200"
            >
              OK
            </button>
          </div>
        </Transition>
      </div>
    </Transition>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import AppLayout from '@/components/layouts/AppLayout.vue'
import AppDropdown from '@/components/Form/AppDropdown.vue'
import VisitorService from '@/services/VisitorService'
import VisitService from '@/services/VisitService'
import api from '@/api/api'
import Swal from 'sweetalert2'
import logger from '../utils/logger'
import { useStats } from '@/composables/useStats'

const router = useRouter()

// Composable para manejar estadísticas según el rol
const { stats, loadHeaderStats } = useStats()

// Datos reactivos
const documentType = ref(1)
const identityDocument = ref('')
const identityDocumentRaw = ref('') // Valor interno sin guiones para cédula
const documentError = ref('')
const phoneRaw = ref('') // Valor interno sin guiones para teléfono
const showForm = ref(false)
const showUserSuggestions = ref(false)
const userSearchResults = ref([])
const isSearchingUsers = ref(false)
const isSavingVisit = ref(false)
const isSearchingVisitor = ref(false)
const searchTimeout = ref(null)
const currentDateTime = reactive({
  date: '',
  time: ''
})

const visitor = reactive({
  id: null,
  identity_document: '',
  name: '',
  lastName: '',
  phone: '',
  email: '',
  institution: ''
})

const visit = reactive({
  namePersonToVisit: '',
  person_to_visit_id: '',
  person_to_visit_email: '',
  send_email: false,
  reason: '',
  department: '',
  building: '',
  floor: '',
  assigned_carnet: '',
  mission_case: false
})

// Variables de estado del modal de éxito
const showSuccessModal = ref(false)
const autoCloseProgress = ref(0)
let autoCloseTimer = null
let autoCloseInterval = null

// Variables de estado del modal de error
const showErrorModal = ref(false)
const errorTitle = ref('Error')
const errorMessage = ref('')

const errors = reactive({
  name: '',
  lastName: '',
  phone: '',
  email: '',
  namePersonToVisit: '',
  reason: '',
  department: '',
  assigned_carnet: '',
  building: '',
  floor: ''
})

const departments = ref([
  'Administrativo',
  'Auditoría Gubernamental',
  'Comunicaciones',
  'Consultoría Jurídica (Área Legal)',
  'Dirección de Planificación y Desarrollo',
  'Dirección Ejecutiva',
  'Financiero',
  'Protección Especial',
  'Protección Legal',
  'Recursos Humanos',
  'Relaciones Interinstitucionales',
  'Tecnología de la Información y Comunicación (TIC)',
  'Hogares de paso',
  'Asociaciones sin fines de lucro (ASFL)',
  'Oficinas de Acceso a la Información (OAI)',
  'Servicio de Comedor',
  'Almacen',
  'Gestión Territorial',
  'Transportación',
  'Servicios Generales',
  'Compras',
  'supervisión Tecnica',
])

// Opciones de edificio (1..4)
const buildingOptions = computed(() => [
  { value: '1', label: '1' },
  { value: '2', label: '2' },
  { value: '3', label: '3' },
  { value: '4', label: '4' }
])

// Opciones de pisos dinámicas según el edificio
const floorOptions = computed(() => {
  const b = Number(visit.building)
  let floors = 0
  switch (b) {
    case 1:
      floors = 4
      break
    case 2:
      floors = 4
      break
    case 3:
      floors = 2
      break
    case 4:
      floors = 1
      break
    default:
      floors = 0
  }
  const opts = []
  for (let i = 1; i <= floors; i++) {
    opts.push({ value: String(i), label: String(i) })
  }
  return opts
})

// Opciones de tipo de documento
const documentTypeOptions = computed(() => [
  { value: 1, label: 'Cédula', description: 'Documento de identidad dominicano' },
  { value: 2, label: 'Pasaporte', description: 'Documento de identidad extranjero' },
  { value: 3, label: 'Sin Identificación', description: 'Sin documento de identidad' }
]);

// Opciones de departamento con índice
const departmentOptions = computed(() =>
  departments.value.map((dept, i) => ({
    value: dept,
    label: `${i + 1}. ${dept}`
  }))
)

// Valida si el documento ingresado cumple con el formato requerido
const isSearchEnabled = computed(() => {
  if (!documentType.value) return false
  
  // Si es tipo 3 (Sin Identificación), no permitir búsqueda
  if (Number(documentType.value) === 3) return false
  
  if (Number(documentType.value) === 1) {
    return identityDocument.value.length === 13 && /^\d{3}-\d{7}-\d{1}$/.test(identityDocument.value)
  } else {
    return identityDocument.value.length >= 8
  }
})

// Retorna el valor del documento ingresado
const displayValue = computed(() => {
  return identityDocument.value
})

// Formatea la cédula con guiones (000-0000000-0)
const formatCedula = (digits) => {
  if (digits.length !== 11) return digits
  return digits.substring(0, 3) + '-' + digits.substring(3, 10) + '-' + digits.substring(10)
}

// Formatea el teléfono con guiones (000-000-0000)
const formatPhone = (phoneDigits) => {
  if (phoneDigits.length !== 10) return phoneDigits
  return phoneDigits.substring(0, 3) + '-' + phoneDigits.substring(3, 6) + '-' + phoneDigits.substring(6, 10)
}

// Limpia el documento ingresado y reinicia el formulario
const clearIdentityDocument = () => {
  // Si es tipo 3 (Sin Identificación), mostrar directamente el formulario
  if (Number(documentType.value) === 3) {
    visitor.id = null
    visitor.name = ''
    visitor.lastName = ''
    visitor.phone = ''
    visitor.email = ''
    visitor.institution = ''
    visitor.identity_document = ''
    showForm.value = true
    clearErrors()
    return
  }
  
  identityDocument.value = ''
  documentError.value = ''
  visitor.identity_document = ''
  showForm.value = false
}

// Formatea el documento ingresado según tipo (cédula o pasaporte)
const handleIdentityInput = (event) => {
  let inputValue = event.target.value
  
  if (Number(documentType.value) === 1) {
    // Extraer solo dígitos
    inputValue = inputValue.replace(/[^0-9]/g, '')
    const digitsOnly = inputValue.substring(0, 11)
    
    // Aplicar formato con guiones de forma dinámica: 000-0000000-0
    let formatted = ''
    for (let i = 0; i < digitsOnly.length; i++) {
      if (i === 3 || i === 10) {
        formatted += '-'
      }
      formatted += digitsOnly[i]
    }
    
    identityDocument.value = formatted
  } else {
    inputValue = inputValue.replace(/[^a-zA-Z0-9]/g, '').toUpperCase()
    identityDocument.value = inputValue
  }
  
  validateDocument()
}

// Formatea el número de teléfono ingresado (000-000-0000)
const handlePhoneInput = (event) => {
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
  
  visitor.phone = formatted
}

// Valida el formato del documento ingresado
const validateDocument = () => {
  if (!identityDocument.value) {
    documentError.value = ''
    return
  }

  if (Number(documentType.value) === 1) {
    if (!/^\d{3}-\d{7}-\d{1}$/.test(identityDocument.value)) {
      documentError.value = 'Formato de cédula inválido (000-0000000-0)'
    } else {
      documentError.value = ''
    }
  } else {
    if (identityDocument.value.length < 8) {
      documentError.value = 'El pasaporte debe tener al menos 8 caracteres'
    } else {
      documentError.value = ''
    }
  }
}

const searchVisitor = async () => {
  if (!isSearchEnabled.value) return

  isSearchingVisitor.value = true
  try {
    const searchValue = identityDocument.value
    visitor.identity_document = searchValue
    
    const visitante = await VisitorService.search(searchValue)
    
    if (visitante) {
      visitor.id = visitante.id
      visitor.name = visitante.name
      visitor.lastName = visitante.lastName
      visitor.phone = visitante.phone || ''
      visitor.email = visitante.email || ''
      visitor.institution = visitante.institution || ''
    } else {
      visitor.id = null
      visitor.name = ''
      visitor.lastName = ''
      visitor.phone = ''
      visitor.email = ''
      visitor.institution = ''
    }

    showForm.value = true
    clearErrors()
  } catch (error) {
    logger.error('Error searching visitor', error)
    showErrorNotification(
      'Error de Búsqueda',
      'No se pudo buscar el visitante. Por favor intente nuevamente.'
    )
  } finally {
    isSearchingVisitor.value = false
  }
}

// Busca usuarios con autocomplete en la base de datos
const searchUsers = () => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value)
  }

  const searchTerm = visit.namePersonToVisit.trim()
  
  if (searchTerm.length < 3) {
    userSearchResults.value = []
    showUserSuggestions.value = false
    return
  }

  isSearchingUsers.value = true
  
  searchTimeout.value = setTimeout(async () => {
    try {
      const response = await api.get('/search-users', {
        params: { query: searchTerm }
      })
      
      userSearchResults.value = response.data.users || []
      showUserSuggestions.value = true
    } catch (error) {
      logger.error('Error searching users', error)
      if (error.response?.status === 401) {
        showErrorNotification(
          'Sesión Expirada',
          'Su sesión ha expirado. Será redirigido al login.'
        )
        setTimeout(() => {
          localStorage.clear()
          router.push('/')
        }, 2000)
      } else {
        userSearchResults.value = []
        showUserSuggestions.value = false
      }
    } finally {
      isSearchingUsers.value = false
    }
  }, 400)
}

// Completa el formulario con los datos del usuario seleccionado
const selectUser = (user) => {
  if (!user) return

  visit.namePersonToVisit = user.displayName || ''
  visit.person_to_visit_id = user.id || ''
  visit.person_to_visit_email = user.mail || user.userPrincipalName || ''
  // visit.department = user.department || ''
  visit.send_email = false
  showUserSuggestions.value = false

  clearErrors()
}

// Muestra las sugerencias cuando el campo tiene focus
const handleFocus = () => {
  if (visit.namePersonToVisit.trim().length >= 3 && userSearchResults.value.length > 0) {
    showUserSuggestions.value = true
  }
}

// Oculta las sugerencias cuando pierde el focus
const handleBlur = () => {
  setTimeout(() => {
    showUserSuggestions.value = false
  }, 200)
}

// Limpia todos los mensajes de error
const clearErrors = () => {
  Object.keys(errors).forEach(key => {
    errors[key] = ''
  })
}

// Limpia el error de un campo específico
const clearFieldError = (fieldName) => {
  if (errors[fieldName]) {
    errors[fieldName] = ''
  }
}

// Maneja el input del campo "Persona a Visitar" con búsqueda reactiva
const handlePersonToVisitInput = () => {
  clearFieldError('namePersonToVisit')
  searchUsers()
}

// React to building selection changes to update floors and auto-select default floor when needed.
watch(() => visit.building, (newVal) => {
  // If building is 4, force floor to 1
  const allowed = floorOptions.value.map(o => o.value)
  if (!newVal) {
    // If building cleared, clear floor
    visit.floor = ''
  } else {
    // If current floor is not allowed for the selected building, set to first allowed option (or empty)
    if (visit.floor && !allowed.includes(String(visit.floor))) {
      visit.floor = allowed.length > 0 ? allowed[0] : ''
    }
    // If selected building has only one floor (e.g. building 4), ensure the floor is set to that single option
    if (allowed.length === 1) {
      visit.floor = allowed[0]
    }
  }
  clearFieldError('building')
})

// Ensure that selecting an invalid floor (for the current building) resets it to the first valid option
watch(() => visit.floor, (newFloor) => {
  const allowed = floorOptions.value.map(o => o.value)
  if (!newFloor) return
  if (!allowed.includes(String(newFloor))) {
    visit.floor = allowed.length > 0 ? allowed[0] : ''
  }
})
 

// Valida que todos los campos obligatorios sean correctos
const validateForm = () => {
  let isValid = true
  clearErrors()

  if (!visitor.name?.trim()) {
    errors.name = 'El nombre es obligatorio'
    isValid = false
  }

  if (!visitor.lastName?.trim()) {
    errors.lastName = 'El apellido es obligatorio'
    isValid = false
  }

  if (visitor.phone?.trim() && !isValidPhone(visitor.phone)) {
    errors.phone = 'Formato de teléfono inválido'
    isValid = false
  }

  if (visitor.email?.trim() && !isValidEmail(visitor.email)) {
    errors.email = 'Formato de correo inválido'
    isValid = false
  }

  // identity_document es obligatorio SOLO si no es tipo 3
  if (Number(documentType.value) !== 3 && !visitor.identity_document?.trim()) {
    errors.identity_document = 'El documento de identidad es obligatorio'
    isValid = false
  }

  if (!visit.mission_case && !visit.namePersonToVisit?.trim()) {
    errors.namePersonToVisit = 'La persona a visitar es obligatoria'
    isValid = false
  }

  if (!visit.mission_case && !visit.department?.trim()) {
    errors.department = 'El departamento es obligatorio'
    isValid = false
  }

  if (!visit.mission_case && !visit.building?.toString().trim()) {
    errors.building = 'El edificio es obligatorio'
    isValid = false
  }

  if (!visit.mission_case && !visit.floor?.toString().trim()) {
    errors.floor = 'El piso es obligatorio'
    isValid = false
  }

  if (!visit.mission_case) {
    const trimmedReason = visit.reason?.trim()
    if (!trimmedReason) {
      errors.reason = 'El motivo de la visita es obligatorio'
      isValid = false
    }
  }

  if (!visit.mission_case) {
    if (!visit.assigned_carnet) {
      errors.assigned_carnet = 'El número de carnet es obligatorio'
      isValid = false
    } else {
      const carnetNum = parseInt(visit.assigned_carnet)
      if (carnetNum < 1 || carnetNum > 99) {
        errors.assigned_carnet = 'El carnet debe estar entre 1 y 99'
        isValid = false
      }
    }
  }

  return isValid
}

// Valida el formato del teléfono (000-000-0000)
const isValidPhone = (phone) => {
  if (!phone) return true
  return /^\d{3}-\d{3}-\d{4}$/.test(phone)
}

// Valida el formato del correo electrónico
const isValidEmail = (email) => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

// Guarda la visita en la base de datos
const saveVisit = async () => {
  if (!validateForm()) {
    showErrorNotification(
      'Campos Obligatorios Incompletos',
      'Por favor complete todos los campos obligatorios antes de continuar.'
    )
    return
  }

  isSavingVisit.value = true

  try {
    const userDataStr = localStorage.getItem('user')
    const userData = userDataStr ? JSON.parse(userDataStr) : null
    
    if (!userData || !userData.id) {
      throw new Error('No se pudo obtener la información del usuario autenticado')
    }

    let visitorId = visitor.id
    
    if (!visitorId) {
      // Crear nuevo visitante si no existe
      const newVisitor = await VisitorService.create({
        identity_document: Number(documentType.value) === 3 ? null : visitor.identity_document,
        name: visitor.name,
        lastName: visitor.lastName,
        phone: visitor.phone,
        email: visitor.email,
        institution: visitor.institution,
        document_type: documentType.value
      })
      visitorId = newVisitor.id

      // Asegurar que el estado local se actualice para evitar re-creaciones
      visitor.id = newVisitor.id
      visitor.identity_document = newVisitor.identity_document ?? visitor.identity_document
      visitor.name = newVisitor.name ?? visitor.name
      visitor.lastName = newVisitor.lastName ?? visitor.lastName
      visitor.phone = newVisitor.phone ?? visitor.phone
      visitor.email = newVisitor.email ?? visitor.email
      visitor.institution = newVisitor.institution ?? visitor.institution

    } else {
      // Actualizar visitante existente
      await VisitorService.update(visitorId, {
        identity_document: Number(documentType.value) === 3 ? null : visitor.identity_document,
        name: visitor.name,
        lastName: visitor.lastName,
        phone: visitor.phone,
        email: visitor.email,
        institution: visitor.institution,
        document_type: documentType.value
      })
    }

    // Preparar datos para crear la visita
    const visitData = {
      user_id: userData.id,
      namePersonToVisit: visit.mission_case ? 'Unidad de Gestión de Casos' : visit.namePersonToVisit,
      person_to_visit_id: visit.mission_case ? null : visit.person_to_visit_id,
      person_to_visit_email: visit.mission_case ? 'casos@demo.example.org' : visit.person_to_visit_email,
      reason: visit.mission_case ? 'Visita derivada a Gestion de Casos' : visit.reason,
      department: visit.mission_case ? 'Unidad de Gestión de Casos' : visit.department,
      building: visit.building ? parseInt(visit.building) : null,
      floor: visit.floor ? parseInt(visit.floor) : null,
      assigned_carnet: visit.assigned_carnet ? parseInt(visit.assigned_carnet) : null,
      mission_case: visit.mission_case,
      send_email: visit.mission_case ? false : visit.send_email,
      visitor_ids: [visitorId]
    }

    // Crear la visita en la base de datos
    await VisitService.create(visitData)

    // Mostrar notificación de éxito
    showSuccessNotification()
  } catch (error) {
    logger.error('Error saving visit', error)
    let errorMsg = 'Error al guardar la visita. Intente nuevamente.'
    
    if (error.response?.data?.message) {
      errorMsg = error.response.data.message
    } else if (error.response?.data?.errors) {
      const errorList = Object.entries(error.response.data.errors)
        .map(([field, messages]) => `${field}: ${messages.join(', ')}`)
        .join('\n')
      errorMsg = errorList
    }

    showErrorNotification('Error', errorMsg)
  } finally {
    isSavingVisit.value = false
  }
}

const cancelForm = () => {
  router.push('/visits')
}

// Muestra el modal de éxito con auto-cierre después de 2.5s
const showSuccessNotification = () => {
  showSuccessModal.value = true
  autoCloseProgress.value = 0

  const updateInterval = 25
  const totalTime = 2500
  const increment = (100 / totalTime) * updateInterval

  autoCloseInterval = setInterval(() => {
    autoCloseProgress.value = Math.min(100, autoCloseProgress.value + increment)
  }, updateInterval)

  autoCloseTimer = setTimeout(() => {
    closeModalAndRedirect('/')
  }, totalTime)
}

const closeModalAndRedirect = (path) => {
  // Clear timers
  if (autoCloseTimer) {
    clearTimeout(autoCloseTimer)
    autoCloseTimer = null
  }
  if (autoCloseInterval) {
    clearInterval(autoCloseInterval)
    autoCloseInterval = null
  }

  showSuccessModal.value = false

  setTimeout(() => {
    router.push(path)
  }, 200)
}

// Redirige a visitas al hacer click en OK
const handleOkClick = () => {
  closeModalAndRedirect('/visits')
}

// Redirige a visitas al hacer click fuera del modal
const handleBackdropClick = () => {
  closeModalAndRedirect('/visits')
}

// Muestra el modal de error
const showErrorNotification = (title, message) => {
  errorTitle.value = title
  errorMessage.value = message
  showErrorModal.value = true
}

// Cierra el modal de error
const closeErrorModal = () => {
  showErrorModal.value = false
}

// Actualiza la fecha y hora actual en el formulario
const updateDateTime = () => {
  const now = new Date()
  const options = { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric',
    timeZone: 'America/Santo_Domingo'
  }
  
  currentDateTime.date = now.toLocaleDateString('es-DO', options)
  currentDateTime.time = now.toLocaleTimeString('es-DO', { 
    hour: '2-digit', 
    minute: '2-digit',
    timeZone: 'America/Santo_Domingo'
  })
}

// Reacta a cambios en el caso misional para autocompletar campos
watch(() => visit.mission_case, (newValue) => {
  if (newValue) {
    visit.namePersonToVisit = 'Unidad de Gestión de Casos'
    visit.person_to_visit_id = null
    visit.person_to_visit_email = 'casos@demo.example.org'
    visit.department = 'Unidad de Gestión de Casos'
    visit.building = ''
    visit.floor = ''
    visit.reason = 'Visita derivada a Gestion de Casos'
    visit.send_email = false
    visit.assigned_carnet = null
    clearErrors()
  } else {
    visit.namePersonToVisit = ''
    visit.person_to_visit_id = ''
    visit.person_to_visit_email = ''
    visit.department = ''
    visit.building = ''
    visit.floor = ''
    visit.reason = ''
    visit.send_email = false
    visit.assigned_carnet = null
  }
})

// Variables para intervalos que necesitan cleanup
let dateTimeInterval = null;

// Lifecycle
onMounted(() => {
  updateDateTime()
  dateTimeInterval = setInterval(updateDateTime, 1000)
  loadHeaderStats()
})

onUnmounted(() => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value)
  }
  // Clean up modal timers
  if (autoCloseTimer) {
    clearTimeout(autoCloseTimer)
  }
  if (autoCloseInterval) {
    clearInterval(autoCloseInterval)
  }
  // Clean up date/time interval
  if (dateTimeInterval) {
    clearInterval(dateTimeInterval)
    dateTimeInterval = null
  }
})
</script>

<style scoped>
/* Modal Transitions */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-scale-enter-active,
.modal-scale-leave-active {
  transition: all 0.2s ease;
}

.modal-scale-enter-from {
  opacity: 0;
  transform: scale(0.9);
}

.modal-scale-leave-to {
  opacity: 0;
  transform: scale(0.95);
}

/* Simple scale-in animation for icon */
@keyframes scaleIn {
  0% {
    transform: scale(0);
  }
  50% {
    transform: scale(1.05);
  }
  100% {
    transform: scale(1);
  }
}

.animate-scale-in {
  animation: scaleIn 0.3s ease-out;
}
</style>

