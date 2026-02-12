<template>
  <div class="w-full h-full flex flex-col">
    <!-- Loading State -->
    <div v-if="loading" class="space-y-4 py-4">
      <!-- Peak indicator skeleton -->
      <div class="flex items-center justify-center">
        <div class="h-8 bg-gradient-to-r from-gray-200 to-gray-100 rounded-lg w-48"></div>
      </div>
      
      <!-- Chart skeleton with animated bars -->
      <div class="flex-1 flex flex-col gap-2 bg-gradient-to-b from-transparent to-gray-50/30 rounded-lg p-4 border border-gray-100">
        <div class="flex-1 flex items-end justify-between gap-1 px-4 pb-4">
          <div v-for="i in 24" :key="i" class="flex-1 flex flex-col items-center">
            <!-- Animated bar -->
            <div 
              class="w-full bg-gradient-to-t from-gray-300 via-gray-200 to-gray-150 rounded-t opacity-0 animate-bar-skeleton"
              :style="{ 
                height: `${20 + (i % 8) * 10}%`,
                animationDelay: `${(i % 6) * 100}ms`
              }"
            ></div>
          </div>
        </div>
        
        <!-- Shimmer overlay -->
        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 animate-skeleton-shimmer rounded-lg pointer-events-none"></div>
      </div>
      
      <!-- Summary skeleton -->
      <div class="flex items-center justify-between px-4 pt-2 text-xs border-t border-gray-200 gap-4">
        <div class="flex gap-4">
          <div v-for="i in 2" :key="i" class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-sm bg-gray-200"></div>
            <div class="h-2 bg-gray-200 rounded w-12"></div>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <div class="h-2 bg-gray-200 rounded w-32"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!data || data.length === 0" class="flex-1 flex flex-col items-center justify-center text-gray-400">
      <Icon icon="mdi:clock-time-four-outline" class="w-16 h-16 mb-2" />
      <p class="text-sm">No hay datos de visitas por hora</p>
    </div>

    <!-- Chart -->
    <div v-else class="flex-1 flex flex-col gap-2">
      <!-- Peak indicator badge - Fixed positioning above chart -->
      <div class="flex items-center justify-center mb-1">
        <div
          v-if="peakHour"
          class="bg-demo-pink-100 text-demo-pink-700 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-md"
        >
          <Icon icon="mdi:fire" class="w-3.5 h-3.5" />
          <span>Pico: {{ peakHour.label }} ({{ peakHour.visits }} visitas)</span>
        </div>
      </div>

      <!-- SVG Chart Area -->
      <div ref="chartAreaRef" class="flex-1 relative bg-gradient-to-b from-transparent to-gray-50/30 rounded-lg p-4 border border-gray-100">
        <svg 
          :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
          class="w-full h-full"
          preserveAspectRatio="none"
        >
          <defs>
            <linearGradient id="blueGradient" x1="0%" y1="100%" x2="0%" y2="0%">
              <stop offset="0%" style="stop-color: #3b82f6; stop-opacity: 1" />
              <stop offset="100%" style="stop-color: #60a5fa; stop-opacity: 1" />
            </linearGradient>
            <linearGradient id="pinkGradient" x1="0%" y1="100%" x2="0%" y2="0%">
              <stop offset="0%" style="stop-color: #ec4899; stop-opacity: 1" />
              <stop offset="100%" style="stop-color: #f472b6; stop-opacity: 1" />
            </linearGradient>
          </defs>

          <!-- Grid lines -->
          <line x1="0" :y1="chartHeight * 0.75" :x2="chartWidth" :y2="chartHeight * 0.75" stroke="#e5e7eb" stroke-width="1" />
          <line x1="0" :y1="chartHeight * 0.5" :x2="chartWidth" :y2="chartHeight * 0.5" stroke="#e5e7eb" stroke-width="1" />
          <line x1="0" :y1="chartHeight * 0.25" :x2="chartWidth" :y2="chartHeight * 0.25" stroke="#e5e7eb" stroke-width="1" />

          <!-- Bars -->
          <g v-for="(hour, index) in data" :key="hour.hour">
            <rect
              :x="index * barWidth + barGap / 2"
              :y="chartHeight - (hour.visits / maxVisits) * chartHeight"
              :width="barWidth - barGap"
              :height="Math.max((hour.visits / maxVisits) * chartHeight, 2)"
              :fill="hour.visits === peakHour.visits ? 'url(#pinkGradient)' : 'url(#blueGradient)'"
              rx="2"
              class="hover:opacity-80 transition-opacity cursor-pointer"
              @mouseenter="(e) => showTooltip(index, e)"
              @mouseleave="hideTooltip"
              style="filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.05));"
            />
            <!-- tooltip is rendered as HTML overlay to avoid SVG scaling issues -->
          </g>
        </svg>

        <!-- HTML tooltip (overlay) -->
        <div
          v-if="tooltip.visible"
          class="absolute z-30 pointer-events-none"
          :style="{
            left: `${tooltip.left}px`,
            top: `${Math.max(8, tooltip.top - 64)}px`,
            transform: 'translateX(-50%)'
          }"
        >
          <div class="bg-gray-900 text-white text-[12px] px-3 py-2 rounded-md shadow-lg min-w-[120px] text-center">
            <div class="font-semibold">{{ tooltip.label }}</div>
            <div class="text-gray-300 text-[11px]">{{ tooltip.visits }} visitas</div>
          </div>
        </div>
      </div>

  <!-- Hour labels removed per UX request -->

      <!-- Summary stats -->
      <div class="flex items-center justify-between px-4 pt-2 text-xs text-gray-600 border-t border-gray-200">
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-1.5">
            <div class="w-3 h-3 rounded-sm bg-gradient-to-t from-blue-500 to-blue-400"></div>
            <span>Normal</span>
          </div>
          <div class="flex items-center gap-1.5">
            <div class="w-3 h-3 rounded-sm bg-gradient-to-t from-pink-500 to-pink-400"></div>
            <span>Pico</span>
          </div>
        </div>
        <div class="text-gray-500">
          Promedio: <span class="font-semibold text-gray-700">{{ averageVisits }}</span> visitas/hora
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  data: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
});

// tooltip overlay state and chart area ref
const chartAreaRef = ref(null);
const tooltip = ref({ visible: false, left: 0, top: 0, label: '', visits: 0 });

function showTooltip(index, e) {
  if (!chartAreaRef.value) return;
  const rect = chartAreaRef.value.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const y = e.clientY - rect.top;
  const hour = props.data && props.data[index] ? props.data[index] : { label: '', visits: 0 };
  tooltip.value.left = x;
  // place tooltip above the cursor / bar and clamp so it doesn't go above the chart
  tooltip.value.top = Math.max(12, y - 56);
  tooltip.value.label = hour.label;
  tooltip.value.visits = hour.visits;
  tooltip.value.visible = true;
}

function hideTooltip() {
  tooltip.value.visible = false;
}

// SVG Chart dimensions - fixed coordinates for proper scaling
const chartWidth = 1200;
const chartHeight = 250;
const barWidth = computed(() => chartWidth / 24);
const barGap = 4;

// Computed properties
const maxVisits = computed(() => {
  if (!props.data || props.data.length === 0) return 1;
  const max = Math.max(...props.data.map(h => h.visits));
  return Math.max(max, 1);
});

const peakHour = computed(() => {
  if (!props.data || props.data.length === 0) return null;
  return props.data.reduce((max, hour) => hour.visits > max.visits ? hour : max, props.data[0]);
});

const averageVisits = computed(() => {
  if (!props.data || props.data.length === 0) return 0;
  const total = props.data.reduce((sum, hour) => sum + hour.visits, 0);
  return Math.round(total / props.data.length);
});
</script>

<style scoped>
svg {
  overflow: visible;
}

/* Skeleton bar animation */
@keyframes bar-skeleton {
  0% {
    opacity: 0.5;
    transform: scaleY(0.5);
  }
  50% {
    opacity: 0.8;
  }
  100% {
    opacity: 0.6;
    transform: scaleY(1);
  }
}

.animate-bar-skeleton {
  animation: bar-skeleton 1.2s ease-in-out infinite;
  transform-origin: bottom;
}

/* Skeleton shimmer effect */
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
