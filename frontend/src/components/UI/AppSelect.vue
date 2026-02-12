<template>
  <div class="app-select-wrapper" :class="{ 'w-full': fullWidth }">
    <label v-if="label" :for="selectId" class="block text-sm font-medium text-gray-700 mb-1.5">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
    </label>
    
    <div class="relative">
      <div v-if="iconLeft" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none z-10">
        <Icon :icon="iconLeft" class="w-5 h-5" />
      </div>
      
      <select
        :id="selectId"
        ref="selectRef"
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        :class="selectClasses"
        @change="handleChange"
        @blur="handleBlur"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option
          v-for="option in options"
          :key="getOptionValue(option)"
          :value="getOptionValue(option)"
        >
          {{ getOptionLabel(option) }}
        </option>
      </select>
      
      <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
        <Icon icon="mdi:chevron-down" class="w-5 h-5" />
      </div>
    </div>
    
    <p v-if="error" class="mt-1.5 text-sm text-red-600 flex items-center gap-1">
      <Icon icon="mdi:alert-circle" class="w-4 h-4" />
      {{ error }}
    </p>
    
    <p v-else-if="hint" class="mt-1.5 text-sm text-gray-500">
      {{ hint }}
    </p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  modelValue: [String, Number, Boolean],
  label: String,
  placeholder: String,
  options: {
    type: Array,
    required: true
  },
  optionLabel: {
    type: String,
    default: 'label'
  },
  optionValue: {
    type: String,
    default: 'value'
  },
  error: String,
  hint: String,
  iconLeft: String,
  disabled: Boolean,
  required: Boolean,
  fullWidth: { type: Boolean, default: true }
});

const emit = defineEmits(['update:modelValue', 'change', 'blur']);

const selectRef = ref(null);
const selectId = computed(() => `select-${Math.random().toString(36).substr(2, 9)}`);

const selectClasses = computed(() => [
  'block w-full rounded-lg border transition-all duration-base appearance-none',
  'px-4 py-2.5 text-sm',
  'focus:outline-none focus:ring-2 focus:ring-offset-1',
  {
    'pl-10': props.iconLeft,
    'pr-10': true,
    'border-red-300 focus:ring-red-500 focus:border-red-500': props.error,
    'border-gray-300 focus:ring-primary-500 focus:border-primary-500': !props.error && !props.disabled,
    'bg-gray-50 cursor-not-allowed text-gray-500': props.disabled
  }
]);

const getOptionValue = (option) => {
  return typeof option === 'object' ? option[props.optionValue] : option;
};

const getOptionLabel = (option) => {
  return typeof option === 'object' ? option[props.optionLabel] : option;
};

const handleChange = (e) => {
  const value = e.target.value;
  emit('update:modelValue', value);
  emit('change', value);
};

const handleBlur = (e) => {
  emit('blur', e);
};

defineExpose({
  focus: () => selectRef.value?.focus()
});
</script>
