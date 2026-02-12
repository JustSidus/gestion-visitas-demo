<template>
  <div class="flex flex-col items-center justify-center py-12 px-4 text-center">
    <div
      class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
      :class="iconBgClass"
    >
      <Icon :icon="icon" class="w-10 h-10" :class="iconColorClass" />
    </div>
    
    <h3 class="text-lg font-semibold text-gray-900 mb-2">
      {{ title }}
    </h3>
    
    <p class="text-sm text-gray-500 max-w-md mb-6">
      {{ description }}
    </p>
    
    <slot name="action">
      <AppButton
        v-if="actionText"
        :icon-left="actionIcon"
        @click="$emit('action')"
      >
        {{ actionText }}
      </AppButton>
    </slot>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';
import AppButton from './AppButton.vue';

const props = defineProps({
  icon: {
    type: String,
    default: 'mdi:inbox'
  },
  title: {
    type: String,
    default: 'No hay datos'
  },
  description: {
    type: String,
    default: 'No se encontraron resultados para mostrar'
  },
  actionText: String,
  actionIcon: String,
  variant: {
    type: String,
    default: 'neutral',
    validator: (v) => ['primary', 'neutral'].includes(v)
  }
});

defineEmits(['action']);

const variants = {
  primary: { bg: 'bg-primary-50', color: 'text-primary-600' },
  neutral: { bg: 'bg-gray-100', color: 'text-gray-400' }
};

const iconBgClass = computed(() => variants[props.variant].bg);
const iconColorClass = computed(() => variants[props.variant].color);
</script>
