<template>
  <span :class="badgeClasses">
    <Icon v-if="icon" :icon="icon" :class="iconSizeClass" />
    <slot />
    <button
      v-if="removable"
      type="button"
      @click="$emit('remove')"
      class="ml-1 hover:bg-black/10 rounded-full p-0.5 transition-colors"
    >
      <Icon icon="mdi:close" :class="iconSizeClass" />
    </button>
  </span>
</template>

<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'neutral',
    validator: (v) => ['primary', 'secondary', 'success', 'warning', 'danger', 'neutral'].includes(v)
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v)
  },
  icon: String,
  removable: Boolean,
  rounded: Boolean
});

defineEmits(['remove']);

const variantClasses = {
  primary: 'bg-primary-50 text-primary-700 border-primary-200',
  secondary: 'bg-secondary-50 text-secondary-700 border-secondary-200',
  success: 'bg-green-50 text-green-700 border-green-200',
  warning: 'bg-yellow-50 text-yellow-700 border-yellow-200',
  danger: 'bg-red-50 text-red-700 border-red-200',
  neutral: 'bg-gray-100 text-gray-700 border-gray-300'
};

const sizeClasses = {
  sm: 'px-2 py-0.5 text-xs gap-1',
  md: 'px-2.5 py-1 text-sm gap-1.5',
  lg: 'px-3 py-1.5 text-base gap-2'
};

const iconSizeClass = computed(() => {
  const sizes = { sm: 'w-3 h-3', md: 'w-3.5 h-3.5', lg: 'w-4 h-4' };
  return sizes[props.size];
});

const badgeClasses = computed(() => [
  'inline-flex items-center font-medium border',
  variantClasses[props.variant],
  sizeClasses[props.size],
  props.rounded ? 'rounded-full' : 'rounded-md'
]);
</script>
