<template>
  <div class="page-header">
    <!-- Breadcrumbs -->
    <nav v-if="breadcrumbs && breadcrumbs.length > 0" class="breadcrumb mb-3">
      <router-link
        v-for="(crumb, index) in breadcrumbs"
        :key="index"
        :to="crumb.to"
        class="breadcrumb-item"
      >
        {{ crumb.label }}
        <Icon 
          v-if="index < breadcrumbs.length - 1" 
          icon="mdi:chevron-right" 
          class="inline w-4 h-4 mx-1 breadcrumb-separator" 
        />
      </router-link>
    </nav>

    <!-- Header Content -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <!-- Title and Subtitle -->
      <div class="flex-1">
        <h1 class="page-title">
          <Icon v-if="icon" :icon="icon" class="inline-block w-7 h-7 mr-2 -mt-1 text-demo-blue-600" />
          {{ title }}
        </h1>
        <p v-if="subtitle" class="page-subtitle">
          {{ subtitle }}
        </p>
      </div>

      <!-- Actions Slot -->
      <div v-if="$slots.actions" class="flex items-center gap-3 flex-shrink-0">
        <slot name="actions" />
      </div>
    </div>

    <!-- Additional Content Slot -->
    <div v-if="$slots.default" class="mt-6">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { Icon } from '@iconify/vue';

defineProps({
  /**
   * Título principal de la página
   */
  title: {
    type: String,
    required: true
  },
  
  /**
   * Subtítulo o descripción breve
   */
  subtitle: {
    type: String,
    default: ''
  },
  
  /**
   * Ícono a mostrar junto al título (nombre de Iconify)
   */
  icon: {
    type: String,
    default: ''
  },
  
  /**
   * Array de breadcrumbs
   * Formato: [{ label: 'Inicio', to: '/' }, { label: 'Visitas', to: '/visits' }]
   */
  breadcrumbs: {
    type: Array,
    default: () => []
  }
});
</script>

<style scoped>
/* Estilos adicionales si son necesarios */
</style>
