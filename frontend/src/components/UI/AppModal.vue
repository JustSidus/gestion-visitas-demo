<!--
  ╔══════════════════════════════════════════════════════════════════════════════╗
  ║ AppModal.vue - Modal Genérico Reutilizable                                  ║
  ║                                                                              ║
  ║ PROPÓSITO:                                                                   ║
  ║ Componente modal base genérico para toda la aplicación. Proporciona una     ║
  ║ estructura consistente (header + contenido flexible mediante slots) que     ║
  ║ puede ser reutilizada en cualquier parte del sistema.                       ║
  ║                                                                              ║
  ║ USADO EN:                                                                    ║
  ║ • UserManagement.vue - Formulario de agregar usuarios                       ║
  ║ • Cualquier otra vista que necesite un modal genérico                       ║
  ║                                                                              ║
  ║ CARACTERÍSTICAS:                                                             ║
  ║  Header personalizable con prop 'title'                                    ║
  ║  Contenido completamente flexible mediante slot por defecto                ║
  ║  Backdrop con cierre al hacer clic fuera                                   ║
  ║  Botón de cerrar (X) en header                                             ║
  ║  Scroll vertical si el contenido excede 90vh                               ║
  ║  Responsive y accesible                                                    ║
  ║  Z-index apropiado para overlay                                            ║
  ║                                                                              ║
  ║ USO:                                                                         ║
  ║ AppModal con title y slot de contenido personalizable                       ║
  ║                                                                              ║
  ║ DIFERENCIA CON VisitModal:                                                   ║
  ║ • AppModal: Genérico con slots - Para formularios, confirmaciones, etc.     ║
  ║ • VisitModal: Especializado - Solo para detalles de visitas                 ║
  ║                                                                              ║
  ║ AUTOR: Sistema Institución Demo - Gestión de Visitas                        ║
  ║ ÚLTIMA ACTUALIZACIÓN: 2025-11-13                                            ║
  ╚══════════════════════════════════════════════════════════════════════════════╝
-->

<template>
  <div 
    class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 z-[9999] overflow-y-auto"
    style="position: fixed; top: 0; left: 0; right: 0; bottom: 0;"
    @click="$emit('close')"
  >
    <div 
      class="bg-white rounded-xl max-w-2xl w-full p-6 shadow-2xl relative my-8"
      style="max-height: calc(100vh - 4rem);"
      @click.stop
    >
      <!-- Header -->
      <div class="flex justify-between items-center mb-4 pb-4 border-b sticky top-0 bg-white z-10">
        <h3 class="text-xl font-semibold text-gray-900">
          {{ title }}
        </h3>
        <button 
          @click="$emit('close')" 
          class="text-gray-400 hover:text-gray-500 transition-colors"
        >
          <i class='bx bx-x text-2xl'></i>
        </button>
      </div>

      <!-- Content Slot -->
      <div class="overflow-y-auto" style="max-height: calc(100vh - 12rem);">
        <slot></slot>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * Props del componente
 */
defineProps({
  /**
   * Título mostrado en el header del modal
   * @type {String}
   * @default 'Modal'
   */
  title: {
    type: String,
    default: 'Modal'
  }
});

/**
 * Eventos emitidos
 * @event close - Se emite cuando el usuario cierra el modal (clic en X o backdrop)
 */
defineEmits(['close']);
</script>

<style scoped>
/* Estilos adicionales si son necesarios */
</style>

