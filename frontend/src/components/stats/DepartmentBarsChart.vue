<template>
  <div class="w-full">
    <!-- Loading State -->
    <div v-if="loading" class="space-y-4 py-2">
      <!-- Title skeleton -->
      <div class="h-3 bg-gradient-to-r from-gray-200 to-gray-100 rounded-full w-48"></div>
      
      <!-- Bar skeletons with animated gradient -->
      <div v-for="i in 6" :key="i" class="space-y-2">
        <!-- Label skeleton -->
        <div class="flex items-center justify-between">
          <div class="h-3 bg-gradient-to-r from-gray-200 to-gray-100 rounded-full w-32"></div>
          <div class="h-3 bg-gradient-to-r from-gray-200 to-gray-100 rounded-full w-16"></div>
        </div>
        
        <!-- Bar skeleton with gradient animation -->
        <div class="relative w-full h-8 bg-gradient-to-r from-gray-100 via-gray-150 to-gray-100 rounded-lg overflow-hidden">
          <div 
            class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 animate-skeleton-pulse"
            :style="{ animationDelay: `${i * 80}ms` }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!data || data.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-400">
      <Icon icon="mdi:office-building-outline" class="w-16 h-16 mb-2" />
      <p class="text-sm">No hay datos de departamentos</p>
    </div>

    <!-- Chart -->
    <div v-else class="space-y-3">
      <div
        v-for="(item, index) in sortedData"
        :key="index"
        class="group"
        :class="{ 'animate-slide-in': animate }"
        :style="{ animationDelay: `${index * 80}ms` }"
      >
        <!-- Department label -->
        <div class="flex items-center justify-between mb-1">
          <span class="text-sm font-medium text-gray-700 truncate">
            {{ item.department }}
          </span>
          <span class="text-xs text-gray-500 ml-2 flex items-center gap-1">
            <span class="font-semibold text-demo-blue-700">{{ item.visits }}</span>
            <span class="text-gray-400">·</span>
            <span>{{ item.percentage }}%</span>
          </span>
        </div>

        <!-- Bar container -->
        <div class="relative w-full h-8 bg-gray-100 rounded-lg overflow-hidden">
          <!-- Animated bar -->
          <div
            class="absolute inset-y-0 left-0 rounded-lg transition-all duration-700 ease-out"
            :class="[
              'bg-gradient-to-r',
              index === 0 ? 'from-demo-blue-500 to-demo-blue-600' : 'from-demo-blue-400 to-demo-blue-500'
            ]"
            :style="{ width: animate ? `${barWidth(item)}%` : '0%' }"
          >
            <!-- Shine effect on hover -->
            <div class="absolute inset-0 bg-white/0 group-hover:bg-white/10 transition-colors duration-200"></div>
          </div>

          <!-- Percentage label inside bar (if wide enough) -->
          <div
            v-if="barWidth(item) > 15"
            class="absolute inset-0 flex items-center justify-end pr-3 text-white text-xs font-semibold pointer-events-none"
          >
            {{ item.visits }}
          </div>
        </div>
      </div>
    </div>

    <!-- Legend/Summary -->
    <div v-if="!loading && data && data.length > 0" class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
      <span>Total de departamentos: {{ data.length }}</span>
      <span>Total de visitas: {{ totalVisits }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue';
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

const animate = ref(false);

// Computed
const sortedData = computed(() => {
  if (!props.data) return [];
  return [...props.data].sort((a, b) => b.visits - a.visits);
});

const maxVisits = computed(() => {
  if (!props.data || props.data.length === 0) return 0;
  return Math.max(...props.data.map(d => d.visits));
});

const totalVisits = computed(() => {
  if (!props.data || props.data.length === 0) return 0;
  return props.data.reduce((sum, d) => sum + d.visits, 0);
});

// Methods
const barWidth = (item) => {
  if (maxVisits.value === 0) return 0;
  return (item.visits / maxVisits.value) * 100;
};

// Lifecycle
onMounted(() => {
  setTimeout(() => {
    animate.value = true;
  }, 100);
});

watch(() => props.data, () => {
  animate.value = false;
  setTimeout(() => {
    animate.value = true;
  }, 50);
}, { deep: true });
</script>

<style scoped>
@keyframes slide-in {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.animate-slide-in {
  animation: slide-in 0.4s ease-out forwards;
}

/* Skeleton pulse animation */
@keyframes skeleton-pulse {
  0% {
    transform: translateX(-100%);
    opacity: 0;
  }
  50% {
    opacity: 1;
  }
  100% {
    transform: translateX(100%);
    opacity: 0;
  }
}

.animate-skeleton-pulse {
  animation: skeleton-pulse 2s infinite;
}
</style>
