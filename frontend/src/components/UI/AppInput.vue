<template>
  <div class="app-input-wrapper" :class="{ 'w-full': fullWidth }">
    <label v-if="label" :for="inputId" class="block text-sm font-medium text-gray-700 mb-1.5">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
    </label>
    
    <div class="relative">
      <div v-if="iconLeft" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
        <Icon :icon="iconLeft" class="w-5 h-5" />
      </div>
      
      <input
        :id="inputId"
        ref="inputRef"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :autocomplete="autocomplete"
        :class="inputClasses"
        @input="handleInput"
        @blur="handleBlur"
        @focus="handleFocus"
      />
      
      <div v-if="iconRight || clearable && modelValue" class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1">
        <button
          v-if="clearable && modelValue"
          type="button"
          @click="handleClear"
          class="text-gray-400 hover:text-gray-600 transition-colors"
        >
          <Icon icon="mdi:close-circle" class="w-5 h-5" />
        </button>
        <Icon v-if="iconRight" :icon="iconRight" class="w-5 h-5 text-gray-400" />
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
  modelValue: [String, Number],
  label: String,
  type: { type: String, default: 'text' },
  placeholder: String,
  error: String,
  hint: String,
  iconLeft: String,
  iconRight: String,
  disabled: Boolean,
  readonly: Boolean,
  required: Boolean,
  clearable: Boolean,
  fullWidth: { type: Boolean, default: true },
  autocomplete: String
});

const emit = defineEmits(['update:modelValue', 'blur', 'focus', 'clear']);

const inputRef = ref(null);
const inputId = computed(() => `input-${Math.random().toString(36).substr(2, 9)}`);

const inputClasses = computed(() => [
  'block w-full rounded-lg border transition-all duration-base',
  'px-4 py-2.5 text-sm',
  'placeholder:text-gray-400',
  'focus:outline-none focus:ring-2 focus:ring-offset-1',
  {
    'pl-10': props.iconLeft,
    'pr-10': props.iconRight || (props.clearable && props.modelValue),
    'border-red-300 focus:ring-red-500 focus:border-red-500': props.error,
    'border-gray-300 focus:ring-primary-500 focus:border-primary-500': !props.error && !props.disabled,
    'bg-gray-50 cursor-not-allowed text-gray-500': props.disabled,
    'bg-gray-50': props.readonly && !props.disabled
  }
]);

const handleInput = (e) => {
  emit('update:modelValue', e.target.value);
};

const handleBlur = (e) => {
  emit('blur', e);
};

const handleFocus = (e) => {
  emit('focus', e);
};

const handleClear = () => {
  emit('update:modelValue', '');
  emit('clear');
  inputRef.value?.focus();
};

defineExpose({
  focus: () => inputRef.value?.focus(),
  blur: () => inputRef.value?.blur()
});
</script>
