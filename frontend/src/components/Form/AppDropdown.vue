<template>
  <div class="relative" ref="dropdown">
    <!-- Modo Searchable: Input de búsqueda -->
    <div v-if="searchable">
      <div class="relative">
        <!-- Icon -->
        <div v-if="icon" class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
          <Icon :icon="icon" class="w-5 h-5 text-gray-400" />
        </div>
        
        <!-- Search Input -->
        <input
          ref="searchInput"
          type="text"
          v-model="searchText"
          @input="handleSearchInput"
          @focus="openDropdownForSearch"
          @blur="handleSearchBlur"
          @keydown="handleSearchKeydown"
          :placeholder="placeholder || 'Busque y seleccione...'"
          :disabled="disabled"
          autocomplete="off"
          :class="[
            'relative w-full py-3 border rounded-xl focus:ring-2 focus:border-transparent transition-all text-sm bg-white',
            icon ? 'pl-12 pr-10' : 'px-4 pr-10',
            disabled ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : '',
            error ? 'border-red-300 bg-red-50 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500',
            variant === 'success' ? 'border-green-300 focus:ring-green-500 focus:border-green-500' : '',
            variant === 'warning' ? 'border-yellow-300 focus:ring-yellow-500 focus:border-yellow-500' : '',
            customClass
          ]"
        />
        
        <!-- Dropdown Arrow -->
        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
          <Icon 
            icon="mdi:chevron-down" 
            class="w-5 h-5 text-gray-400 transition-transform duration-200" 
            :class="{ 'rotate-180': isOpen }"
          />
        </div>
      </div>
    </div>

    <!-- Modo Normal: Botón select -->
    <button
      v-else
      type="button"
      tabindex="0"
      @click="toggleDropdown"
      @keydown="handleDropdownKeydown"
      :disabled="disabled"
      :class="[
        'relative w-full py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent transition-all text-sm bg-white cursor-pointer text-left',
        icon ? 'pl-12 pr-10' : 'px-4 pr-10',
        disabled ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : 'hover:border-gray-400',
        error ? 'border-red-300 bg-red-50 focus:ring-red-500' : 'focus:ring-blue-500 focus:border-blue-500',
        variant === 'success' ? 'border-green-300 focus:ring-green-500 focus:border-green-500' : '',
        variant === 'warning' ? 'border-yellow-300 focus:ring-yellow-500 focus:border-yellow-500' : '',
        isOpen ? 'ring-2 ring-blue-500 border-blue-500' : '',
        customClass
      ]"
    >
      <!-- Icon (if provided) -->
      <div v-if="icon" class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
        <Icon :icon="icon" class="w-5 h-5 text-gray-400" />
      </div>
      
      <!-- Selected Value or Placeholder -->
      <span :class="[
        'block truncate',
        !modelValue && placeholder ? 'text-gray-500' : 'text-gray-900'
      ]">
        {{ displayValue || placeholder || 'Seleccione una opción' }}
      </span>
      
      <!-- Dropdown Arrow -->
      <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
        <Icon 
          icon="mdi:chevron-down" 
          class="w-5 h-5 text-gray-400 transition-transform duration-200" 
          :class="{ 'rotate-180': isOpen }"
        />
      </div>
    </button>

    <!-- Dropdown Menu -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform scale-95 opacity-0"
        enter-to-class="transform scale-100 opacity-100"
        leave-active-class="transition duration-75 ease-in"
        leave-from-class="transform scale-100 opacity-100"
        leave-to-class="transform scale-95 opacity-0"
      >
        <div
          v-if="isOpen && filteredOptions.length > 0"
          :style="dropdownStyle"
          :data-dropdown-id="dropdownId"
          class="fixed z-[9999] bg-white shadow-xl border border-gray-200 rounded-xl max-h-60 overflow-auto focus:outline-none"
          @mousedown.prevent
        >
          <div class="py-1">
            <button
              v-for="(option, index) in filteredOptions"
              :key="option.value || index"
              type="button"
              @click="selectOption(option)"
              @mouseenter="highlightedIndex = index"
              :class="[
                'relative w-full px-4 py-3 text-left text-sm cursor-pointer transition-all duration-75',
                modelValue === option.value 
                  ? 'bg-blue-100 text-blue-900 font-medium' 
                  : 'text-gray-900',
                highlightedIndex === index ? 'bg-blue-600 text-white font-semibold shadow-lg' : 'hover:bg-blue-50',
                option.disabled ? 'opacity-50 cursor-not-allowed' : ''
              ]"
              :disabled="option.disabled"
            >
              <div class="flex items-center justify-between">
                <span class="block truncate">{{ option.label || option.text || option }}</span>
                <Icon 
                  v-if="modelValue === option.value" 
                  icon="mdi:check" 
                  :class="[highlightedIndex === index ? 'w-4 h-4 text-white ml-2 flex-shrink-0' : 'w-4 h-4 text-blue-600 ml-2 flex-shrink-0']"
                />
              </div>
              <div
                v-if="option.description"
                :class="[
                  'text-xs mt-1',
                  highlightedIndex === index ? 'text-white' : (modelValue === option.value ? 'text-blue-800' : 'text-gray-500'),
                  option.disabled ? 'opacity-60' : ''
                ]"
              >
                {{ option.description }}
              </div>
            </button>
          </div>
        </div>
        
        <!-- Mensaje cuando no hay resultados (solo en modo searchable) -->
        <div
          v-else-if="isOpen && searchable && searchText && filteredOptions.length === 0"
          :style="dropdownStyle"
          :data-dropdown-id="dropdownId"
          class="fixed z-[9999] bg-white shadow-xl border border-gray-200 rounded-xl focus:outline-none"
          @mousedown.prevent
        >
          <div class="px-4 py-3 text-gray-500 text-center text-sm">
            No se encontraron resultados para "{{ searchText }}"
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  modelValue: {
    type: [String, Number, Object],
    default: ''
  },
  options: {
    type: Array,
    default: () => []
  },
  icon: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: ''
  },
  disabled: {
    type: Boolean,
    default: false
  },
  error: {
    type: Boolean,
    default: false
  },
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'success', 'warning'].includes(value)
  },
  customClass: {
    type: String,
    default: ''
  },
  searchable: {
    type: Boolean,
    default: false
  },
  caseSensitive: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue']);

const dropdown = ref(null);
const searchInput = ref(null);
const isOpen = ref(false);
const highlightedIndex = ref(-1);
const dropdownStyle = ref({});
const searchQuery = ref(''); // Para búsqueda por teclado (modo no-searchable)
const searchText = ref(''); // Para input visual (modo searchable)
const searchTimeout = ref(null);
const dropdownId = ref('app-dropdown-' + Math.random().toString(36).slice(2, 9));
const searchMode = ref(null); // 'text' or 'number'
const numericQuery = ref('');

// Computed para opciones filtradas (solo en modo searchable)
const filteredOptions = computed(() => {
  if (!props.searchable || !searchText.value) {
    return props.options;
  }
  
  const query = props.caseSensitive ? searchText.value : searchText.value.toLowerCase();
  
  return props.options.filter(option => {
    const label = typeof option === 'object' 
      ? (option.label || option.text || option.value || '')
      : String(option);
    
    const searchIn = props.caseSensitive ? label : label.toLowerCase();
    
    // Buscar en el texto completo Y en el texto sin números iniciales
    const textWithoutNumbers = searchIn.replace(/^\d+\.\s+/, '');
    
    return searchIn.includes(query) || textWithoutNumbers.includes(query);
  });
});

const displayValue = computed(() => {
  if (!props.modelValue) return '';
  
  const option = props.options.find(opt => 
    (typeof opt === 'object' ? opt.value : opt) === props.modelValue
  );
  
  if (option) {
    return typeof option === 'object' 
      ? (option.label || option.text || option.value)
      : option;
  }
  
  return props.modelValue;
});

// Abrir dropdown para modo searchable
const openDropdownForSearch = () => {
  if (props.disabled) return;
  isOpen.value = true;
  highlightedIndex.value = -1;
  updateDropdownPosition();
};

// Manejar blur en modo searchable
const handleSearchBlur = () => {
  setTimeout(() => {
    isOpen.value = false;
    highlightedIndex.value = -1;
    
    // Restaurar texto si hay un valor seleccionado
    if (props.modelValue) {
      const selectedOption = props.options.find(opt => 
        (typeof opt === 'object' ? opt.value : opt) === props.modelValue
      );
      if (selectedOption) {
        searchText.value = typeof selectedOption === 'object' 
          ? (selectedOption.label || selectedOption.text || selectedOption.value)
          : selectedOption;
      }
    } else {
      searchText.value = '';
    }
  }, 200);
};

// Manejar input de búsqueda
const handleSearchInput = () => {
  isOpen.value = true;
  highlightedIndex.value = -1;
  
  // Limpiar selección si no hay coincidencia exacta
  const exactMatch = props.options.find(opt => {
    const label = typeof opt === 'object' 
      ? (opt.label || opt.text || opt.value)
      : String(opt);
    return label.toLowerCase() === searchText.value.toLowerCase();
  });
  
  if (!exactMatch && props.modelValue) {
    emit('update:modelValue', '');
  }
  
  updateDropdownPosition();
};

// Manejar teclas en modo searchable
const handleSearchKeydown = (event) => {
  const key = event.key;
  
  if (!isOpen.value) {
    if (key === 'Enter' || key === 'ArrowDown') {
      event.preventDefault();
      isOpen.value = true;
      updateDropdownPosition();
    }
    return;
  }
  
  // Navegación
  if (key === 'ArrowDown') {
    event.preventDefault();
    highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredOptions.value.length - 1);
    scrollHighlightedIntoView();
    return;
  }
  
  if (key === 'ArrowUp') {
    event.preventDefault();
    highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1);
    scrollHighlightedIntoView();
    return;
  }
  
  // Seleccionar
  if (key === 'Enter') {
    event.preventDefault();
    if (highlightedIndex.value >= 0 && highlightedIndex.value < filteredOptions.value.length) {
      selectOption(filteredOptions.value[highlightedIndex.value]);
    }
    return;
  }
  
  // Cerrar
  if (key === 'Escape') {
    event.preventDefault();
    isOpen.value = false;
    searchInput.value?.blur();
    return;
  }
};

const toggleDropdown = () => {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    highlightedIndex.value = props.options.findIndex(opt => 
      (typeof opt === 'object' ? opt.value : opt) === props.modelValue
    );
    searchQuery.value = '';
    numericQuery.value = '';
    searchMode.value = null;
    setTimeout(() => {
      updateDropdownPosition();
    }, 0);
  }
};

const updateDropdownPosition = () => {
  const element = props.searchable ? searchInput.value : dropdown.value;
  if (element) {
    const rect = element.getBoundingClientRect();
    
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      left: `${rect.left}px`,
      width: `${rect.width}px`,
      maxWidth: '100vw',
      margin: 0,
      padding: 0
    };
  }
};

const closeDropdown = () => {
  isOpen.value = false;
  highlightedIndex.value = -1;
  searchQuery.value = '';
  numericQuery.value = '';
  searchMode.value = null;
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = null;
  }
};

const selectOption = (option) => {
  if (option.disabled) return;
  
  const value = typeof option === 'object' ? option.value : option;
  const label = typeof option === 'object' 
    ? (option.label || option.text || option.value)
    : option;
  
  emit('update:modelValue', value);
  
  // Actualizar texto de búsqueda si está en modo searchable
  if (props.searchable) {
    searchText.value = label;
  }
  
  closeDropdown();
};

const highlightNext = () => {
  const maxIndex = props.searchable ? filteredOptions.value.length - 1 : props.options.length - 1;
  if (highlightedIndex.value < maxIndex) {
    highlightedIndex.value++;
  }
};

const highlightPrevious = () => {
  if (highlightedIndex.value > 0) {
    highlightedIndex.value--;
  }
};

// Obtiene el texto de búsqueda de una opción (sin números iniciales)
const getOptionSearchText = (option) => {
  if (typeof option === 'string') {
    return option.toLowerCase();
  }
  
  if (typeof option === 'object') {
    const text = option.label || option.text || option.value || '';
    let searchText = String(text).toLowerCase();
    searchText = searchText.replace(/^\d+\.\s+/, '');
    return searchText;
  }
  
  return String(option).toLowerCase();
};

// Busca una opción por letra o número (solo para modo no-searchable)
const handleKeyboardSearch = (key) => {
  if (!isOpen.value || props.searchable) return;

  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }

  const isNumber = /^[0-9]$/.test(key);

  if (isNumber) {
    if (searchMode.value !== 'number') {
      searchMode.value = 'number';
      numericQuery.value = '';
    }
    numericQuery.value += key;

    const indexNumber = parseInt(numericQuery.value, 10);
    if (!isNaN(indexNumber) && indexNumber >= 1 && indexNumber <= props.options.length) {
      highlightedIndex.value = indexNumber - 1;
      scrollHighlightedIntoView();
    } else {
      const matchedIndexNumeric = props.options.findIndex(option => {
        const label = (typeof option === 'object' ? (option.label || option.text || option.value) : option);
        return String(label).toLowerCase().startsWith(numericQuery.value);
      });
      if (matchedIndexNumeric !== -1) {
        highlightedIndex.value = matchedIndexNumeric;
        scrollHighlightedIntoView();
      }
    }

    searchTimeout.value = setTimeout(() => {
      numericQuery.value = '';
      searchMode.value = null;
    }, 800);
    return;
  }

  // Text search
  if (searchMode.value !== 'text') {
    searchMode.value = 'text';
    searchQuery.value = '';
  }

  searchQuery.value += key.toLowerCase();

  const matchedIndex = props.options.findIndex(option => {
    const searchText = getOptionSearchText(option);
    return searchText.startsWith(searchQuery.value);
  });

  if (matchedIndex !== -1) {
    highlightedIndex.value = matchedIndex;
    scrollHighlightedIntoView();
  }

  searchTimeout.value = setTimeout(() => {
    searchQuery.value = '';
  }, 500);
};

const scrollHighlightedIntoView = async () => {
  await nextTick();
  const menuEl = document.querySelector(`[data-dropdown-id="${dropdownId.value}"]`);
  if (!menuEl) return;
  const optionEls = menuEl.querySelectorAll('button');
  const el = optionEls[highlightedIndex.value];
  if (el) {
    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
};

// Maneja los eventos de teclado en el dropdown (solo para modo no-searchable)
const handleDropdownKeydown = (event) => {
  if (props.searchable) return; // No usar en modo searchable
  
  const key = event.key;

  if (!isOpen.value) {
    if (key === 'Enter' || key === ' ' || key === 'ArrowDown') {
      event.preventDefault();
      toggleDropdown();
      return;
    }

    if (/^[a-z0-9]$/i.test(key)) {
      event.preventDefault();
      toggleDropdown();
      setTimeout(() => {
        handleKeyboardSearch(key);
      }, 0);
      return;
    }

    return;
  }

  if (key === 'ArrowDown') {
    event.preventDefault();
    highlightNext();
    scrollHighlightedIntoView();
    return;
  }

  if (key === 'ArrowUp') {
    event.preventDefault();
    highlightPrevious();
    scrollHighlightedIntoView();
    return;
  }

  if (key === 'Enter') {
    event.preventDefault();
    if (highlightedIndex.value >= 0 && highlightedIndex.value < props.options.length) {
      selectOption(props.options[highlightedIndex.value]);
    }
    return;
  }

  if (key === 'Escape') {
    event.preventDefault();
    closeDropdown();
    return;
  }

  if (/^[a-z0-9]$/i.test(key)) {
    event.preventDefault();
    handleKeyboardSearch(key);
  }
};

const handleClickOutside = (event) => {
  if (dropdown.value && !dropdown.value.contains(event.target)) {
    closeDropdown();
    
    // Restaurar texto en modo searchable
    if (props.searchable && props.modelValue) {
      const selectedOption = props.options.find(opt => 
        (typeof opt === 'object' ? opt.value : opt) === props.modelValue
      );
      if (selectedOption) {
        searchText.value = typeof selectedOption === 'object' 
          ? (selectedOption.label || selectedOption.text || selectedOption.value)
          : selectedOption;
      }
    }
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  window.addEventListener('scroll', updateDropdownPosition, true);
  window.addEventListener('resize', updateDropdownPosition);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('scroll', updateDropdownPosition, true);
  window.removeEventListener('resize', updateDropdownPosition);
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }
});

// Watch para actualizar searchText cuando cambia el valor desde fuera
watch(() => props.modelValue, (newValue) => {
  if (props.searchable && newValue) {
    const selectedOption = props.options.find(opt => 
      (typeof opt === 'object' ? opt.value : opt) === newValue
    );
    if (selectedOption) {
      searchText.value = typeof selectedOption === 'object' 
        ? (selectedOption.label || selectedOption.text || selectedOption.value)
        : selectedOption;
    }
  } else if (props.searchable && !newValue) {
    searchText.value = '';
  }
  
  closeDropdown();
});

watch(highlightedIndex, (newIndex) => {
  if (isOpen.value && newIndex >= 0) {
    scrollHighlightedIntoView();
  }
});
</script>