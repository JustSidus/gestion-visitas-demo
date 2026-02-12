<template>
  <div class="w-full h-full">
    <!-- Loading State -->
    <div v-if="loading" class="space-y-4 px-4 py-4">
      <!-- Header skeleton -->
      <div class="flex items-center justify-between pb-3 border-b border-gray-200">
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <div class="h-4 bg-gradient-to-r from-gray-200 to-gray-100 rounded w-24"></div>
          </div>
          <div class="h-px w-4 bg-gray-300"></div>
          <div class="flex items-center gap-2">
            <div class="h-4 bg-gradient-to-r from-gray-200 to-gray-100 rounded w-24"></div>
          </div>
        </div>
        <div class="h-6 bg-gradient-to-r from-gray-200 to-gray-100 rounded-full w-20"></div>
      </div>
      
      <!-- Bars skeleton -->
      <div class="space-y-3">
        <div v-for="i in 7" :key="i" class="flex items-center gap-3 group">
          <!-- Day label -->
          <div class="w-12 h-3 bg-gradient-to-r from-gray-200 to-gray-100 rounded flex-shrink-0"></div>
          
          <!-- Bars container -->
          <div class="flex-1 flex items-center gap-1">
            <!-- Current week bar -->
            <div 
              class="h-8 bg-gradient-to-r from-gray-200 via-gray-150 to-gray-100 rounded-lg opacity-0 animate-bar-skeleton flex-1"
              :style="{ 
                width: `${30 + (i % 4) * 15}%`,
                animationDelay: `${i * 80}ms`
              }"
            ></div>
            
            <!-- Previous week bar -->
            <div 
              class="h-8 bg-gradient-to-r from-gray-150 to-gray-100 rounded-lg opacity-0 animate-bar-skeleton flex-1"
              :style="{ 
                width: `${25 + ((7-i) % 4) * 12}%`,
                animationDelay: `${i * 80 + 40}ms`
              }"
            ></div>
          </div>
          
          <!-- Change indicator skeleton -->
          <div class="w-12 h-3 bg-gradient-to-r from-gray-200 to-gray-100 rounded flex-shrink-0"></div>
        </div>
      </div>
      
      <!-- Legend skeleton -->
      <div class="flex items-center justify-center gap-6 pt-3 border-t border-gray-200">
        <div v-for="i in 3" :key="i" class="flex items-center gap-2">
          <div class="w-6 h-3 rounded bg-gray-200"></div>
          <div class="h-2 bg-gray-200 rounded w-20"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!data || data.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-400">
      <Icon icon="mdi:calendar-compare" class="w-16 h-16 mb-2" />
      <p class="text-sm">No hay datos de comparación semanal</p>
    </div>

    <!-- Chart -->
    <div v-else class="flex flex-col h-full px-2 sm:px-4 py-4">
      <!-- Header with totals -->
      <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
        <div class="flex items-center gap-3">
          <div class="text-xs sm:text-sm text-gray-600">
            <span class="font-semibold text-gray-900">{{ currentWeekTotal }}</span>
            <span class="ml-1">esta semana</span>
          </div>
          <div class="h-4 w-px bg-gray-300"></div>
          <div class="text-xs sm:text-sm text-gray-600">
            <span class="font-semibold text-gray-500">{{ previousWeekTotal }}</span>
            <span class="ml-1">semana anterior</span>
          </div>
        </div>
        
        <!-- Overall trend -->
        <div 
          class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
          :class="[
            weeklyChange >= 0 
              ? 'bg-green-100 text-green-700' 
              : 'bg-red-100 text-red-700'
          ]"
        >
          <Icon 
            :icon="weeklyChange >= 0 ? 'mdi:trending-up' : 'mdi:trending-down'" 
            class="w-4 h-4" 
          />
          <span>{{ Math.abs(weeklyChange) }}%</span>
        </div>
      </div>

      <!-- Bars -->
      <div class="space-y-2.5">
        <div
          v-for="(day, index) in data"
          :key="day.day"
          class="group"
        >
          <div class="flex items-center gap-2 sm:gap-3">
            <!-- Day label -->
            <div class="w-12 sm:w-16 text-xs sm:text-sm font-semibold text-gray-700 flex-shrink-0">
              {{ day.day }}
            </div>

            <!-- Bars container -->
            <div class="flex-1 flex items-center gap-1 relative">
              <!-- Current week bar -->
              <div class="flex-1 relative">
                <div
                  class="h-8 rounded-lg transition-all duration-500 hover:shadow-md cursor-pointer bar-current relative overflow-hidden"
                  :class="[
                    day.current > day.previous 
                      ? 'bg-gradient-to-r from-demo-green-600 to-demo-green-500' 
                      : day.current < day.previous
                      ? 'bg-gradient-to-r from-demo-blue-600 to-demo-blue-500'
                      : 'bg-gradient-to-r from-gray-500 to-gray-400'
                  ]"
                  :style="{ 
                    width: `${barWidth(day.current, 'current')}%`,
                    animationDelay: `${index * 60}ms`
                  }"
                >
                  <!-- Shine effect -->
                  <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                  
                  <!-- Value -->
                  <div class="absolute inset-0 flex items-center justify-end px-2 text-white text-xs font-semibold">
                    {{ day.current }}
                  </div>
                </div>

                <!-- Tooltip current -->
                <div class="absolute left-0 top-full mt-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                  <div class="bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap shadow-lg">
                    Esta semana: {{ day.current }} visitas
                  </div>
                </div>
              </div>

              <!-- Previous week bar -->
              <div class="flex-1 relative">
                <div
                  class="h-8 rounded-lg bg-gradient-to-r from-gray-300 to-gray-200 transition-all duration-500 hover:shadow-md cursor-pointer bar-previous relative overflow-hidden"
                  :style="{ 
                    width: `${barWidth(day.previous, 'previous')}%`,
                    animationDelay: `${index * 60 + 30}ms`
                  }"
                >
                  <!-- Shine effect -->
                  <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                  
                  <!-- Value -->
                  <div class="absolute inset-0 flex items-center justify-end px-2 text-gray-700 text-xs font-semibold">
                    {{ day.previous }}
                  </div>
                </div>

                <!-- Tooltip previous -->
                <div class="absolute right-0 top-full mt-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                  <div class="bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap shadow-lg">
                    Semana anterior: {{ day.previous }} visitas
                  </div>
                </div>
              </div>

              <!-- Change indicator -->
              <div class="w-12 sm:w-16 flex items-center justify-center flex-shrink-0">
                <div 
                  v-if="day.change !== 0"
                  class="flex items-center gap-0.5 text-xs font-medium"
                  :class="[
                    day.change > 0 
                      ? 'text-green-600' 
                      : 'text-red-600'
                  ]"
                >
                  <Icon 
                    :icon="day.change > 0 ? 'mdi:arrow-up' : 'mdi:arrow-down'" 
                    class="w-3 h-3" 
                  />
                  <span>{{ Math.abs(day.change) }}%</span>
                </div>
                <div v-else class="text-xs text-gray-400">—</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Legend -->
      <div class="flex items-center justify-center gap-4 sm:gap-6 mt-4 pt-3 border-t border-gray-200 text-xs">
        <div class="flex items-center gap-2">
          <div class="w-6 h-3 rounded bg-gradient-to-r from-demo-green-600 to-demo-green-500"></div>
          <span class="text-gray-600">Semana actual (mayor)</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-6 h-3 rounded bg-gradient-to-r from-demo-blue-600 to-demo-blue-500"></div>
          <span class="text-gray-600">Semana actual (menor)</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-6 h-3 rounded bg-gradient-to-r from-gray-300 to-gray-200"></div>
          <span class="text-gray-600">Semana anterior</span>
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

// Computed
const maxValue = computed(() => {
  if (!props.data || props.data.length === 0) return 0;
  
  const allValues = props.data.flatMap(d => [d.current, d.previous]);
  return Math.max(...allValues);
});

const currentWeekTotal = computed(() => {
  if (!props.data || props.data.length === 0) return 0;
  return props.data.reduce((sum, day) => sum + day.current, 0);
});

const previousWeekTotal = computed(() => {
  if (!props.data || props.data.length === 0) return 0;
  return props.data.reduce((sum, day) => sum + day.previous, 0);
});

const weeklyChange = computed(() => {
  if (previousWeekTotal.value === 0) return 0;
  
  const change = ((currentWeekTotal.value - previousWeekTotal.value) / previousWeekTotal.value) * 100;
  return Math.round(change);
});

// Methods
function barWidth(value, type) {
  if (maxValue.value === 0) return 0;
  
  // Calculate percentage width
  const percentage = (value / maxValue.value) * 100;
  
  // Minimum width for visibility
  return Math.max(percentage, value > 0 ? 10 : 0);
}
</script>

<style scoped>
@keyframes slide-in-right {
  from {
    transform: scaleX(0);
    opacity: 0;
  }
  to {
    transform: scaleX(1);
    opacity: 1;
  }
}

.bar-current {
  animation: slide-in-right 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
  transform-origin: left;
}

.bar-previous {
  animation: slide-in-right 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
  transform-origin: left;
}

/* Skeleton bar animation */
@keyframes bar-skeleton {
  0% {
    opacity: 0.5;
    transform: scaleX(0.7);
  }
  50% {
    opacity: 0.8;
  }
  100% {
    opacity: 0.6;
    transform: scaleX(1);
  }
}

.animate-bar-skeleton {
  animation: bar-skeleton 1.2s ease-in-out infinite;
  transform-origin: left;
}
</style>
