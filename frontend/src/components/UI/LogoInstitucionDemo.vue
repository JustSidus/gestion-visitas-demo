<template>
  <div class="inline-flex items-center" :class="containerClasses" :aria-label="ariaLabel" role="img">
    <img
      v-if="!useFallback"
      :src="logoSrc"
      :alt="ariaLabel"
      class="shrink-0 object-contain"
      :class="[height, width, imageClass]"
      @error="handleLogoError"
    />

    <svg
      v-if="useFallback && showIsotipo"
      viewBox="0 0 120 120"
      xmlns="http://www.w3.org/2000/svg"
      class="shrink-0"
      :class="[height, width, imageClass]"
    >
      <defs>
        <linearGradient id="demoGrad" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#1E4E79" />
          <stop offset="100%" stop-color="#2C64B7" />
        </linearGradient>
      </defs>
      <circle cx="60" cy="60" r="54" fill="url(#demoGrad)" />
      <circle cx="42" cy="48" r="10" fill="#ffffff" />
      <circle cx="60" cy="40" r="10" fill="#ffffff" opacity="0.95" />
      <circle cx="78" cy="48" r="10" fill="#ffffff" />
      <path d="M28 78c4-10 12-16 22-16s18 6 22 16" fill="none" stroke="#ffffff" stroke-width="6" stroke-linecap="round"/>
      <path d="M52 78c4-8 10-12 18-12s14 4 18 12" fill="none" stroke="#ffffff" stroke-width="6" stroke-linecap="round" opacity="0.95"/>
      <path d="M22 84c5-12 14-18 26-18" fill="none" stroke="#8BC34A" stroke-width="7" stroke-linecap="round"/>
      <path d="M72 66c8 1 15 8 19 18" fill="none" stroke="#8BC34A" stroke-width="7" stroke-linecap="round"/>
    </svg>

    <div v-if="useFallback && showText" class="ml-3 leading-tight text-left" :class="imageClass">
      <p class="font-extrabold tracking-wide drop-shadow-sm" :class="[textTitleColorClass, textTitleClass]">Institución Demo</p>
      <p class="font-medium drop-shadow-sm" :class="[textSubtitleColorClass, textSubtitleClass]">Gestión de Visitas</p>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'horizontal',
    validator: (v) => ['isotipo', 'horizontal', 'completo'].includes(v),
  },
  height: {
    type: String,
    default: 'h-10',
  },
  width: {
    type: String,
    default: 'w-10',
  },
  imageClass: {
    type: String,
    default: '',
  },
  textTheme: {
    type: String,
    default: 'light',
    validator: (v) => ['light', 'dark'].includes(v),
  },
});

const useFallback = ref(false);

const logoSrc = computed(() => {
  if (props.variant === 'isotipo') {
    return '/isotipo-institucion-demo.png';
  }

  return '/logo-institucion-demo.png';
});

const handleLogoError = () => {
  useFallback.value = true;
};

const showIsotipo = computed(() => ['isotipo', 'horizontal', 'completo'].includes(props.variant));
const showText = computed(() => ['horizontal', 'completo'].includes(props.variant));

const containerClasses = computed(() => {
  if (props.variant === 'isotipo') return 'justify-center';
  return 'justify-start';
});

const textTitleClass = computed(() => (props.variant === 'completo' ? 'text-lg' : 'text-base'));
const textSubtitleClass = computed(() => (props.variant === 'completo' ? 'text-sm' : 'text-xs'));
const textTitleColorClass = computed(() => (props.textTheme === 'light' ? 'text-white' : 'text-demo-blue-700'));
const textSubtitleColorClass = computed(() => (props.textTheme === 'light' ? 'text-demo-blue-100' : 'text-demo-blue-500'));
const ariaLabel = computed(() => `Logo de ${props.variant === 'isotipo' ? 'Institución Demo' : 'Institución Demo - Gestión de Visitas'}`);
</script>
