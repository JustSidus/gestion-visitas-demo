<template>
  <div class="relative overflow-hidden bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <!-- Loading State -->
    <div v-if="loading" class="animate-pulse">
      <div class="flex items-start justify-between">
        <div class="space-y-2 flex-1">
          <!-- Title skeleton -->
          <div class="h-4 bg-gradient-to-r from-gray-200 to-gray-100 rounded w-28 mb-1"></div>
          
          <!-- Value skeleton - matches text-3xl font-bold -->
          <div class="h-9 bg-gradient-to-r from-gray-200 via-gray-150 to-gray-100 rounded w-20"></div>
        </div>
        
        <!-- Icon skeleton - matches w-12 h-12 -->
        <div class="w-12 h-12 bg-gradient-to-br from-gray-200 to-gray-100 rounded-xl flex-shrink-0"></div>
      </div>
      
      <!-- Shimmer effect -->
      <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-0 animate-shimmer"></div>
    </div>

    <!-- Content -->
    <div v-else class="flex items-start justify-between">
      <div class="flex-1">
        <p class="text-sm font-medium text-gray-600 mb-1">{{ title }}</p>
        <p :class="valueClasses" class="transition-all duration-300">
          {{ formattedValue }}
        </p>
        <p v-if="trend" :class="trendClasses" class="text-xs font-medium mt-1 flex items-center gap-1">
          <Icon :icon="trendIcon" class="w-3 h-3" />
          {{ trend }}
        </p>
      </div>
      
      <div :class="iconContainerClasses" class="flex items-center justify-center transition-transform duration-300 hover:scale-110">
        <Icon :icon="icon" class="w-6 h-6" :class="iconColorClasses" />
      </div>
    </div>

    <!-- Decorative element -->
    <div :class="decorativeClasses" class="absolute -bottom-1 -right-1 w-16 h-16 rounded-full opacity-20"></div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  value: {
    type: [Number, String],
    required: true
  },
  icon: {
    type: String,
    required: true
  },
  color: {
    type: String,
    default: 'blue',
    validator: (value) => ['blue', 'green', 'purple', 'orange', 'red', 'pink'].includes(value)
  },
  trend: {
    type: String,
    default: null
  },
  trendDirection: {
    type: String,
    default: 'neutral',
    validator: (value) => ['up', 'down', 'neutral'].includes(value)
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const formattedValue = computed(() => {
  if (typeof props.value === 'number') {
    return props.value.toLocaleString();
  }
  return props.value;
});

const colorMap = {
  blue: {
    icon: 'text-demo-blue-600',
    bg: 'bg-demo-blue-100',
    decorative: 'bg-demo-blue-200'
  },
  green: {
    icon: 'text-demo-green-600',
    bg: 'bg-demo-green-100',
    decorative: 'bg-demo-green-200'
  },
  purple: {
    icon: 'text-purple-600',
    bg: 'bg-purple-100',
    decorative: 'bg-purple-200'
  },
  orange: {
    icon: 'text-orange-600',
    bg: 'bg-orange-100',
    decorative: 'bg-orange-200'
  },
  red: {
    icon: 'text-red-600',
    bg: 'bg-red-100',
    decorative: 'bg-red-200'
  },
  pink: {
    icon: 'text-demo-pink-600',
    bg: 'bg-demo-pink-100',
    decorative: 'bg-demo-pink-200'
  }
};

const iconContainerClasses = computed(() => {
  return `w-12 h-12 rounded-xl ${colorMap[props.color].bg}`;
});

const iconColorClasses = computed(() => {
  return colorMap[props.color].icon;
});

const decorativeClasses = computed(() => {
  return colorMap[props.color].decorative;
});

const valueClasses = computed(() => {
  return 'text-3xl font-bold text-gray-900';
});

const trendClasses = computed(() => {
  if (props.trendDirection === 'up') {
    return 'text-green-600';
  } else if (props.trendDirection === 'down') {
    return 'text-red-600';
  }
  return 'text-gray-600';
});

const trendIcon = computed(() => {
  if (props.trendDirection === 'up') {
    return 'mdi:trending-up';
  } else if (props.trendDirection === 'down') {
    return 'mdi:trending-down';
  }
  return 'mdi:minus';
});
</script>

<style scoped>
/* Hover effect on entire card */
.relative:hover {
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
}

/* Shimmer effect animation */
@keyframes shimmer {
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

.animate-shimmer {
  animation: shimmer 2s infinite;
}
</style>
