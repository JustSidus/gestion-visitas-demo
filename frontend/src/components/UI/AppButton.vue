<template>
  <component
    :is="tag"
    :type="tag === 'button' ? nativeType : undefined"
    :to="to"
    :class="buttonClasses"
    :disabled="disabled || loading"
    @click="handleClick"
  >
    <Icon v-if="iconLeft && !loading" :icon="iconLeft" :class="iconLeftClass" />
    <Icon v-if="loading" icon="mdi:loading" class="animate-spin" :class="iconLeftClass" />
    <span v-if="$slots.default"><slot /></span>
    <Icon v-if="iconRight && !loading" :icon="iconRight" :class="iconRightClass" />
  </component>
</template>

<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'ghost', 'danger', 'success'].includes(v)
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(v)
  },
  iconLeft: String,
  iconRight: String,
  loading: Boolean,
  disabled: Boolean,
  to: String,
  nativeType: {
    type: String,
    default: 'button'
  },
  fullWidth: Boolean,
  rounded: Boolean
});

const emit = defineEmits(['click']);

const tag = computed(() => props.to ? 'router-link' : 'button');

const baseClasses = 'inline-flex items-center justify-center font-medium transition-all duration-base focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

const variantClasses = {
  primary: 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500 active:bg-primary-800',
  secondary: 'bg-secondary-600 text-white hover:bg-secondary-700 focus:ring-secondary-500 active:bg-secondary-800',
  ghost: 'bg-transparent text-gray-700 hover:bg-gray-100 focus:ring-gray-300 active:bg-gray-200',
  danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 active:bg-red-800',
  success: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500 active:bg-green-800'
};

const sizeClasses = {
  xs: 'px-2.5 py-1.5 text-xs gap-1',
  sm: 'px-3 py-2 text-sm gap-1.5',
  md: 'px-4 py-2.5 text-sm gap-2',
  lg: 'px-5 py-3 text-base gap-2',
  xl: 'px-6 py-3.5 text-base gap-2.5'
};

const iconLeftClass = computed(() => {
  const sizes = { xs: 'w-3 h-3', sm: 'w-3.5 h-3.5', md: 'w-4 h-4', lg: 'w-5 h-5', xl: 'w-5 h-5' };
  return sizes[props.size];
});

const iconRightClass = computed(() => iconLeftClass.value);

const buttonClasses = computed(() => [
  baseClasses,
  variantClasses[props.variant],
  sizeClasses[props.size],
  {
    'w-full': props.fullWidth,
    'rounded-full': props.rounded,
    'rounded-lg': !props.rounded
  }
]);

const handleClick = (e) => {
  if (!props.loading && !props.disabled) {
    emit('click', e);
  }
};
</script>
