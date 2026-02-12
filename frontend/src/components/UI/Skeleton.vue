<template>
  <div :class="['animate-pulse', containerClass]">
    <div v-if="variant === 'text'" :class="textClass"></div>
    <div v-else-if="variant === 'circle'" :class="circleClass"></div>
    <div v-else-if="variant === 'rect'" :class="rectClass"></div>
    <div v-else-if="variant === 'card'">
      <div class="bg-gray-200 rounded-lg p-4 space-y-3">
        <div class="h-4 bg-gray-300 rounded w-3/4"></div>
        <div class="h-3 bg-gray-300 rounded"></div>
        <div class="h-3 bg-gray-300 rounded w-5/6"></div>
      </div>
    </div>
    <div v-else-if="variant === 'table'">
      <div class="bg-gray-200 rounded-lg overflow-hidden">
        <div class="h-12 bg-gray-300"></div>
        <div v-for="i in rows" :key="i" class="h-16 bg-gray-200 border-t border-gray-300"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'text',
    validator: (v) => ['text', 'circle', 'rect', 'card', 'table'].includes(v)
  },
  width: String,
  height: String,
  rows: {
    type: Number,
    default: 3
  },
  containerClass: String
});

const textClass = computed(() => [
  'h-4 bg-gray-200 rounded',
  props.width ? '' : 'w-full'
]);

const circleClass = computed(() => [
  'bg-gray-200 rounded-full',
  props.width || props.height ? '' : 'w-12 h-12'
]);

const rectClass = computed(() => [
  'bg-gray-200 rounded-lg',
  props.width || props.height ? '' : 'w-full h-32'
]);
</script>

<style scoped>
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
