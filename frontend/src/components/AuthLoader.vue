<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-gradient-to-br from-demo-blue-50 via-white to-demo-blue-50">
    <div class="text-center">
      <!-- Logo animado -->
      <div class="mb-6 flex justify-center">
        <div class="relative">
          <div class="absolute inset-0 animate-ping rounded-full bg-demo-blue-200 opacity-25"></div>
          <LogoInstitucionDemo 
            variant="isotipo" 
            height="h-20" 
            width="w-20"
            class="relative"
          />
        </div>
      </div>
      
      <!-- Spinner -->
      <div class="mb-4 flex justify-center">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-demo-blue-100 border-t-demo-blue-600"></div>
      </div>
      
      <!-- Mensaje -->
      <p class="text-sm font-medium text-gray-700">{{ message }}</p>
      <p class="mt-1 text-xs text-gray-500">Validando credenciales...</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { LogoInstitucionDemo } from './UI';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  message: {
    type: String,
    default: 'Verificando sesión'
  }
});

// Auto-ocultar después de un timeout de seguridad (10 segundos)
const timeoutId = ref(null);

watch(() => props.show, (newValue) => {
  if (newValue) {
    // Establecer timeout de seguridad
    timeoutId.value = setTimeout(() => {
      console.warn('AuthLoader timeout - forzando ocultación');
    }, 10000);
  } else {
    // Limpiar timeout si se oculta antes
    if (timeoutId.value) {
      clearTimeout(timeoutId.value);
      timeoutId.value = null;
    }
  }
});
</script>

<style scoped>
/* Animación de entrada/salida suave */
.v-enter-active,
.v-leave-active {
  transition: opacity 0.3s ease;
}

.v-enter-from,
.v-leave-to {
  opacity: 0;
}
</style>
