<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="handleBackdropClick"
      >
        <Transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition-all duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="modelValue"
            :class="dialogClasses"
            role="dialog"
            aria-modal="true"
          >
            <!-- Header -->
            <div v-if="!hideHeader" class="flex items-center justify-between p-6 border-b border-gray-200">
              <div class="flex items-center gap-3">
                <div
                  v-if="icon"
                  class="w-10 h-10 rounded-full flex items-center justify-center"
                  :class="iconBgClass"
                >
                  <Icon :icon="icon" class="w-5 h-5" :class="iconColorClass" />
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">
                    {{ title }}
                  </h3>
                  <p v-if="subtitle" class="text-sm text-gray-500 mt-0.5">{{ subtitle }}</p>
                </div>
              </div>
              
              <button
                v-if="!hideClose"
                type="button"
                @click="handleClose"
                class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-lg hover:bg-gray-100"
              >
                <Icon icon="mdi:close" class="w-6 h-6" />
              </button>
            </div>

            <!-- Content -->
            <div :class="contentClasses">
              <slot />
            </div>

            <!-- Footer -->
            <div v-if="!hideFooter" class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
              <slot name="footer">
                <AppButton
                  v-if="!hideCancel"
                  variant="ghost"
                  @click="handleCancel"
                >
                  {{ cancelText }}
                </AppButton>
                <AppButton
                  :variant="confirmVariant"
                  :loading="loading"
                  @click="handleConfirm"
                >
                  {{ confirmText }}
                </AppButton>
              </slot>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';
import AppButton from './AppButton.vue';

const props = defineProps({
  modelValue: Boolean,
  title: String,
  subtitle: String,
  icon: String,
  iconVariant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'success', 'warning', 'danger'].includes(v)
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg', 'xl', 'full'].includes(v)
  },
  hideHeader: Boolean,
  hideFooter: Boolean,
  hideClose: Boolean,
  hideCancel: Boolean,
  confirmText: {
    type: String,
    default: 'Confirmar'
  },
  cancelText: {
    type: String,
    default: 'Cancelar'
  },
  confirmVariant: {
    type: String,
    default: 'primary'
  },
  loading: Boolean,
  persistent: Boolean
});

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel', 'close']);

const sizeClasses = {
  sm: 'max-w-md',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
  full: 'max-w-7xl w-full'
};

const iconVariants = {
  primary: { bg: 'bg-primary-50', color: 'text-primary-600' },
  success: { bg: 'bg-green-50', color: 'text-green-600' },
  warning: { bg: 'bg-yellow-50', color: 'text-yellow-600' },
  danger: { bg: 'bg-red-50', color: 'text-red-600' }
};

const dialogClasses = computed(() => [
  'bg-white rounded-xl shadow-2xl w-full overflow-hidden',
  sizeClasses[props.size]
]);

const contentClasses = computed(() => [
  'p-6',
  { 'max-h-[60vh] overflow-y-auto': props.size !== 'full' }
]);

const iconBgClass = computed(() => iconVariants[props.iconVariant].bg);
const iconColorClass = computed(() => iconVariants[props.iconVariant].color);

const handleBackdropClick = () => {
  if (!props.persistent) {
    handleClose();
  }
};

const handleClose = () => {
  emit('update:modelValue', false);
  emit('close');
};

const handleConfirm = () => {
  emit('confirm');
};

const handleCancel = () => {
  emit('update:modelValue', false);
  emit('cancel');
};
</script>
