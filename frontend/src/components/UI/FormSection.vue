<template>
  <div class="form-section">
    <div v-if="title || description" class="mb-4 pb-4 border-b border-gray-200">
      <div class="flex items-center gap-3 mb-1">
        <div v-if="icon" class="w-8 h-8 rounded-lg bg-primary-50 flex items-center justify-center">
          <Icon :icon="icon" class="w-4 h-4 text-primary-600" />
        </div>
        <h3 class="text-base font-semibold text-gray-900">{{ title }}</h3>
      </div>
      <p v-if="description" class="text-sm text-gray-500" :class="{ 'ml-11': icon }">
        {{ description }}
      </p>
    </div>
    
    <div :class="gridClass">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  title: String,
  description: String,
  icon: String,
  columns: {
    type: Number,
    default: 1,
    validator: (v) => [1, 2, 3].includes(v)
  }
});

const gridClass = computed(() => {
  const cols = {
    1: 'grid grid-cols-1 gap-4',
    2: 'grid grid-cols-1 md:grid-cols-2 gap-4',
    3: 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4'
  };
  return cols[props.columns];
});
</script>
