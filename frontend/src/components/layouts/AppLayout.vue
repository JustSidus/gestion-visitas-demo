<template>
  <div class="min-h-screen bg-gray-50 relative">
    <Sidebar ref="sidebarRef" :stats="stats" @toggle="handleSidebarToggle" :mobile-open="mobileSidebarOpen" @close-mobile="mobileSidebarOpen = false" />
    <Topbar
      :sidebar-collapsed="sidebarCollapsed"
      :stats="stats"
      @open-command-palette="commandPaletteOpen = true"
      @toggle-mobile-sidebar="mobileSidebarOpen = !mobileSidebarOpen"
    />
    
    <main
      class="layout-transition pt-16 min-h-screen"
      :style="{ 
        marginLeft: sidebarWidth,
        width: `calc(100% - ${sidebarWidth})`
      }"
    >
      <div class="p-4 sm:p-6 lg:p-8">
        <slot />
      </div>
    </main>

    <!-- Command Palette -->
    <CommandPalette v-model="commandPaletteOpen" />
    
    <!-- Mobile Sidebar Overlay -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
        leave-to-class="opacity-0"
      >
        <div
          v-if="mobileSidebarOpen"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 md:hidden"
          @click="mobileSidebarOpen = false"
        ></div>
      </Transition>
    </Teleport>
    
    <!-- Modal Backdrop (si se usa desde las vistas) -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
        leave-to-class="opacity-0"
      >
        <div
          v-if="$attrs.showModalBackdrop"
          class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999]"
          @click="$emit('closeModal')"
          style="top: 0; left: 0; right: 0; bottom: 0; position: fixed;"
        ></div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Sidebar from './Sidebar.vue';
import Topbar from './Topbar.vue';
import CommandPalette from '@/components/CommandPalette.vue';

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({ todayVisitors: 0, activeVisits: 0, totalVisitors: 0 })
  }
});

const emit = defineEmits(['closeModal']);

const sidebarRef = ref(null);
const sidebarCollapsed = ref(true); // Inicializar en true (colapsado) para coincidir con Sidebar
const mobileSidebarOpen = ref(false);
const commandPaletteOpen = ref(false);
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);

// Cargar estado del sidebar desde localStorage
onMounted(() => {
  const savedState = localStorage.getItem('sidebar-collapsed');
  if (savedState !== null) {
    sidebarCollapsed.value = savedState === 'true';
  }
});

const sidebarWidth = computed(() => {
  // On mobile (below md breakpoint), sidebar is overlay so no margin needed
  if (windowWidth.value < 768) {
    return '0px';
  }
  // On desktop, use normal collapsed/expanded logic
  return sidebarCollapsed.value ? '72px' : '280px';
});

const handleSidebarToggle = (collapsed) => {
  sidebarCollapsed.value = collapsed;
};

// Command Palette keyboard shortcut
const handleKeydown = (e) => {
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault();
    commandPaletteOpen.value = !commandPaletteOpen.value;
  }
  if (e.key === 'Escape') {
    commandPaletteOpen.value = false;
    mobileSidebarOpen.value = false;
    emit('closeModal');
  }
};

// Handle window resize for responsive behavior
const handleResize = () => {
  windowWidth.value = window.innerWidth;
  // Close mobile sidebar when resizing to desktop
  if (windowWidth.value >= 768) {
    mobileSidebarOpen.value = false;
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
  window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('resize', handleResize);
});
</script>

<style scoped>
/* Transición suave del layout cuando el sidebar cambia */
.layout-transition {
  transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>