<template>
  <div class="w-full h-full">
    <!-- Loading State -->
    <div v-if="loading" class="space-y-3 px-4 py-4">
      <!-- Title skeleton -->
      <div class="h-3 bg-gradient-to-r from-gray-200 to-gray-100 rounded-full w-48 mb-4"></div>
      
      <!-- Bars skeleton -->
      <div class="flex items-end justify-between gap-2 sm:gap-4 h-40">
        <div v-for="i in 7" :key="i" class="flex-1 flex flex-col items-center gap-2">
          <!-- Bar skeleton -->
          <div 
            class="w-full max-w-16 bg-gradient-to-t from-gray-200 via-gray-150 to-gray-100 rounded-t opacity-0 animate-bar-skeleton"
            :style="{ 
              height: `${30 + (i % 5) * 15}%`,
              animationDelay: `${i * 80}ms`
            }"
          ></div>
          
          <!-- Label skeleton -->
          <div class="space-y-1 w-full">
            <div class="h-2 bg-gradient-to-r from-gray-200 to-gray-100 rounded w-6 mx-auto"></div>
            <div class="h-2 bg-gradient-to-r from-gray-200 to-gray-100 rounded w-4 mx-auto"></div>
          </div>
        </div>
      </div>
      
      <!-- Legend skeleton -->
      <div class="flex items-center justify-center gap-4 pt-2 border-t border-gray-200">
        <div v-for="i in 3" :key="i" class="flex items-center gap-1.5">
          <div class="w-3 h-3 rounded bg-gray-200"></div>
          <div class="h-2 bg-gray-200 rounded w-16"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!data || data.length === 0" class="flex flex-col items-center justify-center h-48 text-gray-400">
      <Icon icon="mdi:calendar-week" class="w-16 h-16 mb-2" />
      <p class="text-sm">No hay datos de visitas por día</p>
    </div>

    <!-- Chart -->
    <div v-else class="flex flex-col h-full">
  <!-- Chart area -->
  <!-- Give the chart area a fixed minimum height and allow overflow so tooltips can render outside the bar area -->
  <div class="flex-1 flex items-end justify-between gap-2 sm:gap-4 px-2 sm:px-4 pb-4 relative min-h-[160px] overflow-visible">
        <!-- Bar for each day -->
        <div
          v-for="(day, index) in data"
          :key="day.day"
          class="flex-1 flex flex-col items-center group relative"
        >
          <!-- Bar container -->
          <div class="w-full flex flex-col items-center">
            <!-- Bar -->
            <div
              class="w-full max-w-16 rounded-t-lg transition-all duration-300 hover:shadow-lg cursor-pointer bar-animate relative overflow-hidden"
              :class="[
                day.average === maxAverage 
                  ? 'bg-gradient-to-t from-demo-green-600 to-demo-green-400 shadow-md shadow-demo-green-500/20' 
                  : day.average === minAverage
                  ? 'bg-gradient-to-t from-gray-400 to-gray-300'
                  : 'bg-gradient-to-t from-demo-blue-600 to-demo-blue-400'
              ]"
              :style="{ 
                height: `${barHeight(day.average)}px`,
                animationDelay: `${index * 60}ms`
              }"
            >
              <!-- Shine effect -->
              <div class="absolute inset-0 bg-gradient-to-t from-transparent via-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
              
              <!-- Value badge -->
              <div class="absolute -top-5 left-1/2 transform -translate-x-1/2 text-xs font-semibold text-gray-700 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                {{ day.average }}
              </div>
            </div>

            <!-- Tooltip -->
            <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-8 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
              <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                <div class="font-semibold mb-1">{{ day.label }}</div>
                <div class="text-gray-300">Promedio: {{ day.average }} visitas</div>
                <div v-if="day.average === maxAverage" class="text-demo-green-400 flex items-center gap-1 mt-1">
                  <Icon icon="mdi:trending-up" class="w-3 h-3" />
                  <span>Día más activo</span>
                </div>
                <div v-else-if="day.average === minAverage" class="text-gray-400 flex items-center gap-1 mt-1">
                  <Icon icon="mdi:trending-down" class="w-3 h-3" />
                  <span>Día menos activo</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Day label -->
          <div class="mt-0 text-center leading-tight flex flex-col items-center justify-center">
            <div 
              class="text-xs sm:text-sm font-semibold transition-colors duration-200"
              :class="[
                day.average === maxAverage 
                  ? 'text-demo-green-600' 
                  : day.average === minAverage
                  ? 'text-gray-500'
                  : 'text-gray-700'
              ]"
              style="margin-bottom: 0; line-height: 1;"
            >
              {{ day.day }}
            </div>
            <div class="text-[10px] sm:text-xs text-gray-500 mt-0 truncate h-4 flex items-center justify-center" style="line-height: 1;">
              {{ day.average }}
            </div>
          </div>
        </div>
      </div>

      <!-- Legend -->
      <div class="flex items-center justify-center gap-4 px-4 pt-1 pb-1 text-xs border-t border-gray-200">
        <div class="flex items-center gap-1.5">
          <div class="w-3 h-3 rounded bg-gradient-to-t from-demo-green-600 to-demo-green-400"></div>
          <span class="text-gray-600">Más activo</span>
        </div>
        <div class="flex items-center gap-1.5">
          <div class="w-3 h-3 rounded bg-gradient-to-t from-demo-blue-600 to-demo-blue-400"></div>
          <span class="text-gray-600">Normal</span>
        </div>
        <div class="flex items-center gap-1.5">
          <div class="w-3 h-3 rounded bg-gradient-to-t from-gray-400 to-gray-300"></div>
          <span class="text-gray-600">Menos activo</span>
        </div>
      </div>

      <!-- Stats summary -->
      <div class="flex items-center justify-between px-4 py-0.5 text-xs text-gray-600">
        <div>
          <span class="text-gray-500">Total semanal:</span>
          <span class="ml-1 font-semibold text-gray-700">{{ totalWeekly }}</span>
        </div>
        <div>
          <span class="text-gray-500">Promedio:</span>
          <span class="ml-1 font-semibold text-gray-700">{{ weeklyAverage }}</span> /día
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
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

// Constants
const MAX_BAR_HEIGHT = 120; // pixels
const MIN_BAR_HEIGHT = 20;

// Computed
const maxAverage = computed(() => {
  if (!props.data || props.data.length === 0) return 0;
  return Math.max(...props.data.map(d => d.average));
});

const minAverage = computed(() => {
  if (!props.data || props.data.length === 0) return 0;
  return Math.min(...props.data.map(d => d.average));
});

const totalWeekly = computed(() => {
  if (!props.data || props.data.length === 0) return 0;
  return props.data.reduce((sum, day) => sum + day.average, 0);
});

const weeklyAverage = computed(() => {
  if (!props.data || props.data.length === 0) return 0;
  return Math.round(totalWeekly.value / props.data.length);
});

// Methods
function barHeight(average) {
  if (maxAverage.value === 0) return MIN_BAR_HEIGHT;
  
  // Scale the bar height proportionally
  const ratio = average / maxAverage.value;
  const height = MIN_BAR_HEIGHT + (ratio * (MAX_BAR_HEIGHT - MIN_BAR_HEIGHT));
  
  return Math.max(height, average > 0 ? MIN_BAR_HEIGHT : 0);
}
</script>

<style scoped>
@keyframes bar-slide-up {
  from {
    transform: scaleY(0);
    opacity: 0;
  }
  to {
    transform: scaleY(1);
    opacity: 1;
  }
}

.bar-animate {
  animation: bar-slide-up 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
  transform-origin: bottom;
}

/* Skeleton bar animation */
@keyframes bar-skeleton {
  0% {
    opacity: 0.5;
    transform: scaleY(0.7);
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
</style>
