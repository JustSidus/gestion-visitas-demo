<template>
  <div class="w-full flex flex-col items-center py-4">
    <!-- Loading State -->
    <div v-if="loading" class="w-64 flex flex-col items-center gap-6">
      <!-- Gauge circle skeleton -->
      <div class="relative w-64 h-40 flex items-center justify-center">
        <svg viewBox="0 0 200 120" class="w-full h-full">
          <!-- Background arc -->
          <path
            d="M 40,100 A 60,60 0 0,1 160,100"
            fill="none"
            stroke="#e5e7eb"
            stroke-width="12"
            stroke-linecap="round"
          />
          
          <!-- Colored zones skeleton -->
          <g opacity="0.3">
            <path d="M 40,100 A 60,60 0 0,1 100,55" fill="none" stroke="#d1d5db" stroke-width="12" stroke-linecap="round" />
            <path d="M 100,55 A 60,60 0 0,1 160,100" fill="none" stroke="#d1d5db" stroke-width="12" stroke-linecap="round" />
          </g>
          
          <!-- Animated needle skeleton -->
          <g class="animate-gauge-needle-skeleton">
            <line x1="100" y1="100" x2="100" y2="50" stroke="#d1d5db" stroke-width="3" stroke-linecap="round" />
            <circle cx="100" cy="100" r="6" fill="#e5e7eb" />
          </g>
        </svg>
        
        <!-- Shimmer overlay -->
        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 animate-skeleton-shimmer rounded-full"></div>
      </div>
      
      <!-- Value text skeleton -->
      <div class="text-center space-y-2">
        <div class="h-8 bg-gradient-to-r from-gray-200 to-gray-100 rounded-lg w-24 mx-auto"></div>
        <div class="h-3 bg-gradient-to-r from-gray-200 to-gray-100 rounded-full w-32 mx-auto"></div>
      </div>
      
      <!-- Legend skeleton -->
      <div class="flex items-center justify-center gap-4">
        <div v-for="i in 3" :key="i" class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-full bg-gray-200"></div>
          <div class="h-2 bg-gray-200 rounded w-12"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!duration || !duration.average" class="flex flex-col items-center justify-center py-12 text-gray-400">
      <Icon icon="mdi:clock-outline" class="w-16 h-16 mb-2" />
      <p class="text-sm">No hay datos de duración</p>
    </div>

    <!-- Gauge -->
    <div v-else class="relative w-64 h-40">
      <!-- SVG Gauge -->
      <svg viewBox="0 0 200 120" class="w-full h-full">
        <defs>
          <!-- Gradient for the gauge -->
          <linearGradient id="gaugeGradient" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" style="stop-color: #10b981; stop-opacity: 1" />
            <stop offset="50%" style="stop-color: #f59e0b; stop-opacity: 1" />
            <stop offset="100%" style="stop-color: #ef4444; stop-opacity: 1" />
          </linearGradient>
        </defs>

        <!-- Background arc (gray) -->
        <path
          :d="arcPath"
          fill="none"
          stroke="#e5e7eb"
          stroke-width="12"
          stroke-linecap="round"
        />

        <!-- Colored zones -->
        <!-- Green zone (0-60 min = 1 hour) -->
        <path
          :d="zoneGreenPath"
          fill="none"
          stroke="#10b981"
          stroke-width="12"
          stroke-linecap="round"
          opacity="0.3"
        />

        <!-- Yellow zone (60-120 min = 1-2 hours) -->
        <path
          :d="zoneYellowPath"
          fill="none"
          stroke="#f59e0b"
          stroke-width="12"
          stroke-linecap="round"
          opacity="0.3"
        />

        <!-- Red zone (120+ min = 2+ hours) -->
        <path
          :d="zoneRedPath"
          fill="none"
          stroke="#ef4444"
          stroke-width="12"
          stroke-linecap="round"
          opacity="0.3"
        />

        <!-- Needle -->
        <g :transform="`rotate(${needleAngle} 100 100)`" class="transition-transform duration-1000 ease-out">
          <line
            x1="100"
            y1="100"
            x2="100"
            y2="40"
            stroke="#1E4E79"
            stroke-width="3"
            stroke-linecap="round"
          />
          <circle cx="100" cy="100" r="6" fill="#1E4E79" />
        </g>

        <!-- Center circle -->
        <circle cx="100" cy="100" r="4" fill="#ffffff" stroke="#1E4E79" stroke-width="2" />
      </svg>

      <!-- Value display: moved into SVG to ensure it's always above the needle and scales with the gauge -->
      <!-- We'll render the main numeric value and label inside the SVG so it won't be overlapped by the needle. -->
      <svg viewBox="0 0 200 120" class="w-full h-full absolute inset-0 pointer-events-none">
        <!-- value number (drawn after needle so it overlays) -->
        <text x="100" y="70" text-anchor="middle" class="value-number" fill="#0f172a">
          {{ animatedValue }}
          <tspan class="value-unit" dx="6" font-size="12" fill="#6b7280">min</tspan>
        </text>
        <text x="100" y="92" text-anchor="middle" class="value-label" fill="#6b7280">Duración Promedio</text>
      </svg>
    </div>

    <!-- Zone labels -->
    <div v-if="!loading && duration && duration.average" class="flex items-center justify-center gap-4 mt-4 text-xs">
      <div class="flex items-center gap-1">
        <div class="w-3 h-3 rounded-full bg-green-500"></div>
        <span class="text-gray-600">&lt;1 hora</span>
      </div>
      <div class="flex items-center gap-1">
        <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
        <span class="text-gray-600">1-2 horas</span>
      </div>
      <div class="flex items-center gap-1">
        <div class="w-3 h-3 rounded-full bg-red-500"></div>
        <span class="text-gray-600">&gt;2 horas</span>
      </div>
    </div>

    <!-- Min/Max -->
    <div v-if="!loading && duration && duration.average" class="flex items-center justify-between w-full mt-4 text-xs text-gray-500">
      <span>Mín: {{ duration.min }} min</span>
      <span>Máx: {{ duration.max }} min</span>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  duration: {
    type: Object,
    default: () => ({ average: 0, min: 0, max: 0 })
  },
  loading: {
    type: Boolean,
    default: false
  },
  maxDuration: {
    type: Number,
    default: 120
  }
});

const animatedValue = ref(0);

// Computed
const needleAngle = computed(() => {
  if (!props.duration || !props.duration.average) return -90;
  
  // Map 0-120 min to -90 to 90 degrees
  const percentage = Math.min(props.duration.average / props.maxDuration, 1);
  return -90 + (percentage * 180);
});

const arcPath = computed(() => {
  // Semi-circle arc from -90 to 90 degrees
  return describeArc(100, 100, 80, -90, 90);
});

const zoneGreenPath = computed(() => {
  // 0-60 min zone (0% to 50% of 120 min)
  const endAngle = -90 + (60 / props.maxDuration) * 180;
  return describeArc(100, 100, 80, -90, endAngle);
});

const zoneYellowPath = computed(() => {
  // 60-120 min zone (50% to 100% of 120 min)
  const startAngle = -90 + (60 / props.maxDuration) * 180;
  const endAngle = -90 + (120 / props.maxDuration) * 180;
  return describeArc(100, 100, 80, startAngle, endAngle);
});

const zoneRedPath = computed(() => {
  // 120+ min zone (beyond 100% of 120 min - shown as reaching the end)
  const startAngle = -90 + (120 / props.maxDuration) * 180;
  return describeArc(100, 100, 80, startAngle, 90);
});

// Methods
function describeArc(x, y, radius, startAngle, endAngle) {
  const start = polarToCartesian(x, y, radius, endAngle);
  const end = polarToCartesian(x, y, radius, startAngle);
  const largeArcFlag = endAngle - startAngle <= 180 ? '0' : '1';
  
  return [
    'M', start.x, start.y,
    'A', radius, radius, 0, largeArcFlag, 0, end.x, end.y
  ].join(' ');
}

function polarToCartesian(centerX, centerY, radius, angleInDegrees) {
  const angleInRadians = ((angleInDegrees - 90) * Math.PI) / 180.0;
  
  return {
    x: centerX + (radius * Math.cos(angleInRadians)),
    y: centerY + (radius * Math.sin(angleInRadians))
  };
}

function animateValue() {
  const target = props.duration?.average || 0;
  const duration = 1000;
  const steps = 60;
  const increment = target / steps;
  let current = 0;
  
  const interval = setInterval(() => {
    current += increment;
    if (current >= target) {
      animatedValue.value = Math.round(target);
      clearInterval(interval);
    } else {
      animatedValue.value = Math.round(current);
    }
  }, duration / steps);
}

// Lifecycle
onMounted(() => {
  setTimeout(() => {
    animateValue();
  }, 300);
});

watch(() => props.duration, () => {
  animatedValue.value = 0;
  setTimeout(() => {
    animateValue();
  }, 100);
}, { deep: true });
</script>

<style scoped>
/* Smooth transitions */
svg {
  overflow: visible;
}

/* Value text styles placed inside SVG */
.value-number {
  font-size: 26px;
  font-weight: 700;
  font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
}
.value-unit {
  font-size: 12px;
  font-weight: 600;
}
.value-label {
  font-size: 12px;
  fill: #6b7280;
}

/* Skeleton loading animations */
@keyframes gauge-needle-skeleton {
  0%, 100% {
    opacity: 0.6;
  }
  50% {
    opacity: 0.9;
  }
}

.animate-gauge-needle-skeleton {
  animation: gauge-needle-skeleton 1.5s ease-in-out infinite;
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
