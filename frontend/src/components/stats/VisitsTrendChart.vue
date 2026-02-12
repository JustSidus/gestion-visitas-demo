<template>
  <div class="w-full h-auto flex flex-col">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-32">
      <div class="w-full px-4 space-y-4">
        <!-- Title skeleton -->
        <div class="space-y-2">
          <div class="h-3 bg-gradient-to-r from-gray-200 to-gray-100 rounded-full w-48"></div>
        </div>
        
        <!-- Chart skeleton with wave lines -->
        <div class="relative w-full h-64">
          <svg viewBox="0 0 800 250" preserveAspectRatio="none" class="w-full h-full">
            <!-- Grid lines (faint) -->
            <line x1="0" y1="62" x2="800" y2="62" stroke="#e5e7eb" stroke-width="1" opacity="0.5" />
            <line x1="0" y1="125" x2="800" y2="125" stroke="#e5e7eb" stroke-width="1" opacity="0.5" />
            <line x1="0" y1="188" x2="800" y2="188" stroke="#e5e7eb" stroke-width="1" opacity="0.5" />
            
            <!-- Animated wave skeleton -->
            <g>
              <path
                d="M 0,100 Q 50,80 100,100 T 200,100 T 300,100 T 400,100 T 500,100 T 600,100 T 700,100 T 800,100"
                fill="none"
                stroke="#d1d5db"
                stroke-width="3"
                stroke-linecap="round"
                class="animate-wave-skeleton"
              />
            </g>
            
            <!-- Shimmer overlay -->
            <defs>
              <linearGradient id="skeletonGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="transparent" stop-opacity="0" />
                <stop offset="50%" stop-color="white" stop-opacity="0.3" />
                <stop offset="100%" stop-color="transparent" stop-opacity="0" />
              </linearGradient>
            </defs>
            <rect x="0" y="0" width="800" height="250" fill="url(#skeletonGradient)" class="animate-skeleton-shimmer" />
          </svg>
        </div>
        
        <!-- X-axis labels skeleton -->
        <div class="flex justify-between px-2 gap-2">
          <div v-for="i in 6" :key="i" class="h-3 bg-gradient-to-r from-gray-200 to-gray-100 rounded-full flex-1"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!data.dates || data.dates.length === 0" class="flex flex-col items-center justify-center py-32 text-gray-400">
      <Icon icon="mdi:chart-line-variant" class="w-16 h-16 mb-2" />
      <p class="text-sm">No hay datos disponibles</p>
    </div>

    <!-- Chart -->
    <div v-else class="w-full space-y-4">
      <!-- Chart area with responsive SVG -->
      <div class="w-full relative" style="aspect-ratio: 16 / 6;">
        <svg
          viewBox="0 0 800 300"
          preserveAspectRatio="none"
          class="w-full h-full"
        >
          <!-- Gradient definition -->
          <defs>
            <linearGradient id="areaGradient" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" style="stop-color:#1E4E79;stop-opacity:0.3" />
              <stop offset="100%" style="stop-color:#3B82F6;stop-opacity:0.05" />
            </linearGradient>
          </defs>

          <!-- Area fill -->
          <path
            v-if="areaPath"
            :d="areaPath"
            fill="url(#areaGradient)"
          />

          <!-- Line path -->
          <path
            v-if="linePath"
            :d="linePath"
            stroke="#1E4E79"
            stroke-width="4"
            fill="none"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
          />

          <!-- Data points -->
          <circle
            v-for="(point, index) in chartPoints"
            :key="index"
            :cx="point.x"
            :cy="point.y"
            r="8"
            fill="#1E4E79"
            class="cursor-pointer hover:opacity-80 transition-opacity"
            vector-effect="non-scaling-stroke"
            @mouseenter="showTooltip(index, point)"
            @mouseleave="hideTooltip"
          />
        </svg>

        <!-- Tooltip -->
        <Transition
          enter-active-class="transition-all duration-200"
          enter-from-class="opacity-0 scale-95"
          leave-active-class="transition-all duration-150"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="tooltip.show"
            class="absolute bg-gray-900 text-white text-xs rounded-lg px-3 py-2 pointer-events-none z-10 shadow-lg whitespace-nowrap"
            :style="tooltipStyle"
          >
            <div class="font-semibold mb-1">{{ tooltip.date }}</div>
            <div class="flex items-center gap-2">
              <span>Visitas:</span>
              <span class="font-bold">{{ tooltip.value }}</span>
            </div>
            <div v-if="tooltip.change !== null" class="flex items-center gap-1 mt-1" :class="tooltip.change >= 0 ? 'text-green-400' : 'text-red-400'">
              <Icon :icon="tooltip.change >= 0 ? 'mdi:trending-up' : 'mdi:trending-down'" class="w-3 h-3" />
              <span class="text-xs">{{ Math.abs(tooltip.change).toFixed(1) }}%</span>
            </div>
          </div>
        </Transition>
      </div>

      <!-- X-axis labels -->
      <div class="flex justify-between text-xs text-gray-500 px-2">
        <span v-for="(label, index) in xAxisLabels" :key="index">
          {{ label }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  data: {
    type: Object,
    required: true,
    default: () => ({ dates: [], visits: [] })
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const tooltip = ref({
  show: false,
  x: 0,
  y: 0,
  date: '',
  value: 0,
  change: null
});

// Dimensiones del viewBox SVG (fijas para cálculos)
const SVG_WIDTH = 800;
const SVG_HEIGHT = 300;
const PADDING = { top: 20, right: 20, bottom: 20, left: 20 };

// Preprocesar datos
const processed = computed(() => {
  const dates = Array.isArray(props.data.dates) ? [...props.data.dates] : [];
  const visits = Array.isArray(props.data.visits) ? [...props.data.visits].map(v => Number(v) || 0) : [];
  const minLength = Math.min(dates.length, visits.length);
  
  return {
    dates: dates.slice(0, minLength),
    visits: visits.slice(0, minLength)
  };
});

// Calcular valor máximo con relleno
const maxValue = computed(() => {
  const max = Math.max(...(processed.value.visits || [0]), 1);
  return Math.ceil(max * 1.15); // 15% de relleno
});

// Calcular puntos del gráfico en coordenadas SVG
const chartPoints = computed(() => {
  if (!processed.value.visits || processed.value.visits.length === 0) return [];

  const visits = processed.value.visits;
  const chartWidth = SVG_WIDTH - PADDING.left - PADDING.right;
  const chartHeight = SVG_HEIGHT - PADDING.top - PADDING.bottom;

  return visits.map((value, index) => {
    const x = PADDING.left + (index / Math.max(visits.length - 1, 1)) * chartWidth;
    const y = PADDING.top + (1 - (Number(value) / maxValue.value)) * chartHeight;
    return { x, y, value };
  });
});

// Crear ruta de línea continua
const linePath = computed(() => {
  if (chartPoints.value.length === 0) return '';
  
  const points = chartPoints.value;
  let path = `M ${points[0].x} ${points[0].y}`;
  
  for (let i = 1; i < points.length; i++) {
    path += ` L ${points[i].x} ${points[i].y}`;
  }
  
  return path;
});

// Crear ruta de relleno de área
const areaPath = computed(() => {
  if (chartPoints.value.length === 0) return '';
  
  const points = chartPoints.value;
  const bottom = SVG_HEIGHT - PADDING.bottom;
  
  let path = `M ${PADDING.left} ${bottom}`;
  path += ` L ${points[0].x} ${points[0].y}`;
  
  for (let i = 1; i < points.length; i++) {
    path += ` L ${points[i].x} ${points[i].y}`;
  }
  
  path += ` L ${points[points.length - 1].x} ${bottom}`;
  path += ` Z`;
  
  return path;
});

// Etiquetas del eje X
const xAxisLabels = computed(() => {
  if (!processed.value.dates || processed.value.dates.length === 0) return [];

  const dates = processed.value.dates;
  const step = Math.max(1, Math.ceil(dates.length / 6));
  
  return dates.filter((_, index) => index % step === 0).map(date => {
    const d = new Date(date);
    return `${d.getDate()}/${d.getMonth() + 1}`;
  });
});

// Posicionamiento del tooltip
const tooltipStyle = computed(() => {
  if (!tooltip.value.show) return {};
  
  return {
    left: `${tooltip.value.x}%`,
    top: `${tooltip.value.y}%`,
    transform: 'translate(-50%, -100%) translateY(-8px)'
  };
});

// Métodos
const showTooltip = (index, point) => {
  const value = props.data.visits[index];
  const previousValue = index > 0 ? props.data.visits[index - 1] : null;
  const change = previousValue ? ((value - previousValue) / previousValue) * 100 : null;

  // Convertir coordenadas SVG a porcentaje para posicionamiento responsivo
  const xPercent = (point.x / SVG_WIDTH) * 100;
  const yPercent = (point.y / SVG_HEIGHT) * 100;

  tooltip.value = {
    show: true,
    x: xPercent,
    y: yPercent,
    date: new Date(props.data.dates[index]).toLocaleDateString('es-ES', { 
      day: 'numeric', 
      month: 'short' 
    }),
    value,
    change
  };
};

const hideTooltip = () => {
  tooltip.value.show = false;
};
</script>

<style scoped>
/* Wave animation for skeleton loading */
@keyframes wave-skeleton {
  0%, 100% {
    d: path('M 0,100 Q 50,80 100,100 T 200,100 T 300,100 T 400,100 T 500,100 T 600,100 T 700,100 T 800,100');
  }
  50% {
    d: path('M 0,100 Q 50,120 100,100 T 200,100 T 300,100 T 400,100 T 500,100 T 600,100 T 700,100 T 800,100');
  }
}

.animate-wave-skeleton {
  animation: wave-skeleton 2s ease-in-out infinite;
  opacity: 0.6;
}

@keyframes skeleton-shimmer {
  0% {
    transform: translateX(-100%);
    opacity: 0;
  }
  50% {
    opacity: 0.5;
  }
  100% {
    transform: translateX(100%);
    opacity: 0;
  }
}

.animate-skeleton-shimmer {
  animation: skeleton-shimmer 2s infinite;
}
</style>
