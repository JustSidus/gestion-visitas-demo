<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition-opacity duration-150"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-start justify-center pt-[15vh] px-4"
        @click.self="close"
      >
        <Transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="opacity-0 scale-95 -translate-y-4"
          leave-active-class="transition-all duration-150 ease-in"
          leave-to-class="opacity-0 scale-95 -translate-y-4"
        >
          <div
            v-if="modelValue"
            class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden"
          >
            <!-- Search Input -->
            <div class="flex items-center gap-3 px-4 py-4 border-b border-gray-200">
              <Icon icon="mdi:magnify" class="w-5 h-5 text-gray-400 flex-shrink-0" />
              <input
                ref="searchInputRef"
                v-model="searchQuery"
                type="text"
                placeholder="Buscar acciones, visitas, menús..."
                class="flex-1 bg-transparent border-none outline-none text-sm placeholder:text-gray-400"
                @keydown.down.prevent="moveSelection(1)"
                @keydown.up.prevent="moveSelection(-1)"
                @keydown.enter.prevent="executeSelected"
                @keydown.esc="close"
              />
              <kbd class="hidden sm:inline-block px-2 py-1 bg-gray-100 border border-gray-300 rounded text-xs text-gray-600">
                ESC
              </kbd>
            </div>

            <!-- Results -->
            <div class="max-h-[400px] overflow-y-auto">
              <!-- Quick Actions -->
              <div v-if="filteredActions.length" class="py-2">
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                  Acciones Rápidas
                </div>
                <button
                  v-for="(action, index) in filteredActions"
                  :key="action.id"
                  :class="[
                    'w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors',
                    selectedIndex === index
                      ? 'bg-primary-50 text-primary-700'
                      : 'hover:bg-gray-50 text-gray-700'
                  ]"
                  @click="executeAction(action)"
                  @mouseenter="selectedIndex = index"
                >
                  <div
                    :class="[
                      'w-8 h-8 rounded-lg flex items-center justify-center',
                      selectedIndex === index ? 'bg-primary-100' : 'bg-gray-100'
                    ]"
                  >
                    <Icon
                      :icon="action.icon"
                      class="w-4 h-4"
                      :class="selectedIndex === index ? 'text-primary-600' : 'text-gray-600'"
                    />
                  </div>
                  <span class="text-sm font-medium">{{ action.label }}</span>
                </button>
              </div>

              <!-- Menu Items -->
              <div v-if="filteredMenu.length" class="py-2 border-t border-gray-200">
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                  Navegación
                </div>
                <button
                  v-for="(item, index) in filteredMenu"
                  :key="item.to"
                  :class="[
                    'w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors',
                    selectedIndex === filteredActions.length + index
                      ? 'bg-primary-50 text-primary-700'
                      : 'hover:bg-gray-50 text-gray-700'
                  ]"
                  @click="navigateTo(item.to)"
                  @mouseenter="selectedIndex = filteredActions.length + index"
                >
                  <Icon
                    :icon="item.icon"
                    class="w-5 h-5"
                    :class="selectedIndex === filteredActions.length + index ? 'text-primary-600' : 'text-gray-400'"
                  />
                  <span class="text-sm">{{ item.label }}</span>
                </button>
              </div>

              <!-- Empty State -->
              <div v-if="!filteredActions.length && !filteredMenu.length" class="py-12 text-center">
                <Icon icon="mdi:magnify-close" class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                <p class="text-sm text-gray-500">No se encontraron resultados</p>
                <p class="text-xs text-gray-400 mt-1">Intenta con otros términos de búsqueda</p>
              </div>
            </div>

            <!-- Footer -->
            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-between text-xs text-gray-500">
              <div class="flex items-center gap-4">
                <span class="flex items-center gap-1">
                  <kbd class="px-1.5 py-0.5 bg-white border border-gray-300 rounded">↑</kbd>
                  <kbd class="px-1.5 py-0.5 bg-white border border-gray-300 rounded">↓</kbd>
                  navegar
                </span>
                <span class="flex items-center gap-1">
                  <kbd class="px-1.5 py-0.5 bg-white border border-gray-300 rounded">↵</kbd>
                  seleccionar
                </span>
              </div>
              <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-white border border-gray-300 rounded">ESC</kbd>
                cerrar
              </span>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { Icon } from '@iconify/vue';
import { MENU, QUICK_ACTIONS } from '@/menu';

const props = defineProps({
  modelValue: Boolean
});

const emit = defineEmits(['update:modelValue']);

const router = useRouter();
const searchQuery = ref('');
const selectedIndex = ref(0);
const searchInputRef = ref(null);

const filteredActions = computed(() => {
  if (!searchQuery.value) return QUICK_ACTIONS;
  const query = searchQuery.value.toLowerCase();
  return QUICK_ACTIONS.filter(action =>
    action.label.toLowerCase().includes(query)
  );
});

const filteredMenu = computed(() => {
  if (!searchQuery.value) return MENU;
  const query = searchQuery.value.toLowerCase();
  return MENU.filter(item =>
    item.label.toLowerCase().includes(query)
  );
});

const totalItems = computed(() => filteredActions.value.length + filteredMenu.value.length);

const moveSelection = (delta) => {
  selectedIndex.value = (selectedIndex.value + delta + totalItems.value) % totalItems.value;
};

const executeSelected = () => {
  if (selectedIndex.value < filteredActions.value.length) {
    executeAction(filteredActions.value[selectedIndex.value]);
  } else {
    const menuIndex = selectedIndex.value - filteredActions.value.length;
    navigateTo(filteredMenu.value[menuIndex].to);
  }
};

const executeAction = (action) => {
  if (action.to) {
    navigateTo(action.to);
  } else if (action.action) {
    // Handle custom actions (silently, no console log)
  }
  close();
};

const navigateTo = (path) => {
  router.push(path);
  close();
};

const close = () => {
  emit('update:modelValue', false);
  searchQuery.value = '';
  selectedIndex.value = 0;
};

watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    nextTick(() => {
      searchInputRef.value?.focus();
    });
  }
});

watch(searchQuery, () => {
  selectedIndex.value = 0;
});
</script>
