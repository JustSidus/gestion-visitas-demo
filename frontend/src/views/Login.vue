<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import MicrosoftAuthService from '../services/MicrosoftAuthService';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { LogoInstitucionDemo } from '@/components/UI';
import logger from '../utils/logger';

const router = useRouter();
const error = ref('');
const loadingMicrosoft = ref(false);
const processingRedirect = ref(false);
const showDemoNotice = ref(false);
const demoSecondsLeft = ref(10);
const demoProcessing = ref(false);
let demoTimer = null;

/**
 * Procesar el redirect de MSAL cuando vuelve de Azure AD
 */
onMounted(async () => {
  // Verificar si hay un hash con código de autenticación
  if (window.location.hash.includes('code=') || window.location.hash.includes('id_token=')) {
    processingRedirect.value = true;
    loadingMicrosoft.value = true;
    
    try {
      logger.log('Procesando respuesta de Azure AD...');
      
      // Intentar obtener token y usuario
      let token = await MicrosoftAuthService.getAccessToken();
      const userInfo = await MicrosoftAuthService.getUserInfo();
      
      if (token && userInfo) {
        logger.success('Token obtenido del redirect', userInfo);

        // Enviar al backend con retry ligero si el token no es aceptado a la primera
        const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
        const postLogin = async (attempt = 1, currentToken = token) => {
          try {
            const response = await axios.post(`${API_URL}/auth/microsoft-login`, {
              access_token: currentToken,
              microsoft_user: userInfo
            });

            // Guardar datos
            localStorage.setItem('access_token', response.data.access_token);
            localStorage.setItem('microsoft_access_token', currentToken);
            localStorage.setItem('user', JSON.stringify(response.data.user));
            localStorage.setItem('role', response.data.user.role);

            logger.success('Login completado', response.data.user);

            // Limpiar el hash antes de redirigir
            window.history.replaceState(null, '', window.location.pathname);

            // Redirigir según rol
            const userRole = response.data.user.role;
            if (userRole === 'aux_ugc') {
              router.push('/mission-visits');
            } else {
              router.push('/visits');
            }

            return true;
          } catch (err) {
            // Si el backend reportó token inválido, intentar obtener nuevamente el token MS y reenviar (1 retry)
            const status = err?.response?.status;
            logger.warn(`Intento ${attempt} de login al backend fallido`, { status });
            if ((status === 401 || status === 400) && attempt < 2) {
              // Reintentar obtener token silenciosamente y reenviar
              let retryToken = currentToken;
              try {
                const newToken = await MicrosoftAuthService.getAccessToken();
                if (newToken && newToken !== currentToken) {
                  retryToken = newToken;
                }
              } catch (e) {
                logger.warn('No se pudo renovar token antes de reintentar el login', e);
              }

              // Pequeña espera antes de reintentar
              await new Promise(res => setTimeout(res, 500));
              return postLogin(attempt + 1, retryToken);
            }

            throw err;
          }
        };

        await postLogin();
      } else {
        logger.error('No se pudo obtener token o usuario después del redirect');
        error.value = 'Error al completar la autenticación. Intenta de nuevo.';
        
        // Limpiar MSAL state para evitar loops
        MicrosoftAuthService.clearMsalState();
        window.history.replaceState(null, '', window.location.pathname);
      }
    } catch (err) {
      logger.error('Error procesando redirect', err);
      
      // Si hay error de autenticación, limpiar MSAL
      if (err.response?.status === 400 || err.response?.status === 401) {
        MicrosoftAuthService.clearMsalState();
      }
      
      error.value = err.response?.data?.message || 'Error al procesar la autenticación. Intenta de nuevo.';
      window.history.replaceState(null, '', window.location.pathname);
    } finally {
      processingRedirect.value = false;
      loadingMicrosoft.value = false;
    }
  }
});

onBeforeUnmount(() => {
  if (demoTimer) {
    clearInterval(demoTimer);
    demoTimer = null;
  }
});

/**
 * Login con Microsoft 365 (SSO) - Inicia el redirect O procesa sesión existente
 */
async function handleMicrosoftLogin() {
  error.value = '';
  showDemoNotice.value = true;
  demoSecondsLeft.value = 10;

  if (demoTimer) {
    clearInterval(demoTimer);
  }

  demoTimer = setInterval(() => {
    demoSecondsLeft.value -= 1;

    if (demoSecondsLeft.value <= 0) {
      clearInterval(demoTimer);
      demoTimer = null;
      continueWithDemoLogin();
    }
  }, 1000);
}

async function continueWithDemoLogin() {
  if (demoProcessing.value) {
    return;
  }

  demoProcessing.value = true;
  loadingMicrosoft.value = true;

  try {
    if (demoTimer) {
      clearInterval(demoTimer);
      demoTimer = null;
    }

    const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
    const response = await axios.post(`${API_URL}/auth/demo-login`);

    localStorage.setItem('access_token', response.data.access_token);
    localStorage.setItem('user', JSON.stringify(response.data.user));
    localStorage.setItem('role', response.data.user.role);

    logger.success('Login demo completado', response.data.user);

    const userRole = response.data.user.role;
    if (userRole === 'aux_ugc') {
      router.push('/mission-visits');
    } else {
      router.push('/visits');
    }
  } catch (err) {
    logger.error('Error en login demo', err);
    handleLoginError(err);
  } finally {
    demoProcessing.value = false;
    loadingMicrosoft.value = false;
    showDemoNotice.value = false;
  }
}

// Manejo centralizado de errores
function handleLoginError(err) {
  if (err.response && err.response.data) {
    const errorData = err.response.data;
    
    if (errorData.error === 'No autorizado') {
      error.value = `${errorData.message}\n\nTu correo: ${errorData.email}\n\n${errorData.help}`;
    } else if (errorData.error === 'Cuenta desactivada') {
      error.value = errorData.message;
    } else {
      error.value = errorData.message || 'Error al iniciar sesión con Microsoft';
    }
  } else if (err.message) {
    error.value = err.message;
  } else {
    error.value = 'Error de conexión. Intente nuevamente.';
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-demo-blue-50 via-white to-demo-blue-50 px-4 py-12">
    <div class="w-full max-w-md">
      <!-- Card Container -->
      <div class="bg-white rounded-2xl shadow-institutional border border-gray-100 overflow-hidden">
        <!-- Header con degradado institucional -->
        <div class="bg-gradient-to-br from-demo-blue-400 to-demo-blue-600 px-8 py-10 text-center">
          <div class="flex justify-center mb-6">
            <LogoInstitucionDemo 
            variant="horizontal" 
            height="h-24" 
            width="w-auto"
            image-class="drop-shadow-lg"
            />
          </div>
        </div>

        <!-- Content -->
        <div class="px-8 py-8">
          <!-- Mensaje de Bienvenida -->
          <div class="text-center mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-2">Bienvenido</h2>
            <p class="text-sm text-gray-600">Inicia sesión con tu cuenta institucional de Microsoft 365</p>
          </div>

          <!-- Mensaje de error -->
          <Transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="transition-all duration-150"
            leave-to-class="opacity-0 -translate-y-2"
          >
            <div 
              v-if="error" 
              class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg"
            >
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <div class="flex-1">
                  <p class="text-sm text-red-800 font-medium whitespace-pre-line">{{ error }}</p>
                </div>
              </div>
            </div>
          </Transition>

          <!-- Botón de Microsoft 365 -->
          <button
            type="button"
            @click="handleMicrosoftLogin"
            class="w-full py-3.5 px-4 bg-white hover:bg-gray-50 border-2 border-gray-300 hover:border-gray-400 text-gray-700 font-semibold rounded-xl shadow-sm transition-all duration-200 flex items-center justify-center gap-3 disabled:opacity-60 disabled:cursor-not-allowed group"
            :disabled="loadingMicrosoft"
          >
            <!-- Microsoft Logo -->
            <svg v-if="!loadingMicrosoft" class="w-5 h-5 flex-shrink-0" viewBox="0 0 23 23" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M0 0h11v11H0z" fill="#f25022"/>
              <path d="M12 0h11v11H12z" fill="#00a4ef"/>
              <path d="M0 12h11v11H0z" fill="#ffb900"/>
              <path d="M12 12h11v11H12z" fill="#7fba00"/>
            </svg>
            
            <!-- Loading Spinner -->
            <span v-if="loadingMicrosoft" class="inline-block w-5 h-5 border-2 border-gray-400 border-t-transparent rounded-full animate-spin flex-shrink-0"></span>
            
            <!-- Text -->
            <span v-if="loadingMicrosoft" class="text-sm">Iniciando sesión...</span>
            <span v-else class="text-sm">Continuar con Microsoft 365</span>
          </button>

          <!-- Información adicional -->
          <div class="mt-6 p-4 bg-demo-blue-50 rounded-lg border border-demo-blue-100">
            <div class="flex items-start gap-3">
              <svg class="w-5 h-5 text-demo-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
              <div class="text-xs text-demo-blue-800">
                <p class="font-medium mb-1">Acceso Institucional</p>
                <p class="text-demo-blue-700">Solo personal autorizado de la Institución Demo puede acceder al sistema. Usa tu correo institucional @demo.example.org</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-8 py-6 bg-gray-50 border-t border-gray-100">
          <div class="flex items-center justify-center gap-2 text-center">
            <LogoInstitucionDemo 
              variant="isotipo" 
              height="h-10" 
              width="w-10"
            />
            <p class="text-xs text-gray-500">
              © {{ new Date().getFullYear() }} Institución Demo
            </p>
          </div>
        </div>
      </div>

      <!-- Indicador de carga alternativo -->
      <Transition
        enter-active-class="transition-all duration-300"
        enter-from-class="opacity-0 translate-y-2"
        leave-active-class="transition-all duration-200"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div v-if="loadingMicrosoft" class="mt-6 text-center">
          <div class="inline-flex items-center gap-3 px-6 py-3 bg-white rounded-xl shadow-md border border-gray-200">
            <div class="w-5 h-5 border-2 border-demo-blue-600 border-t-transparent rounded-full animate-spin"></div>
            <span class="text-sm text-gray-700 font-medium">Autenticando con Microsoft...</span>
          </div>
        </div>
      </Transition>
    </div>
  </div>

  <Transition
    enter-active-class="transition-opacity duration-200"
    enter-from-class="opacity-0"
    leave-active-class="transition-opacity duration-150"
    leave-to-class="opacity-0"
  >
    <div v-if="showDemoNotice" class="fixed inset-0 z-[70] bg-black/40 backdrop-blur-[1px] flex items-center justify-center p-4">
      <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-gray-200 p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-3">Acceso Microsoft deshabilitado por anonimización</h3>
        <p class="text-sm text-gray-700 mb-2">
          En esta parte normalmente se colocan las credenciales de Microsoft 365 y se realiza la autenticación.
        </p>
        <p class="text-sm text-gray-700 mb-5">
          Como esta versión fue anonimizada para portafolio, esa integración ya no está activa.
        </p>

        <div class="flex items-center justify-between gap-3">
          <span class="text-xs text-gray-500">Continuación automática en {{ demoSecondsLeft }}s</span>
          <button
            type="button"
            @click="continueWithDemoLogin"
            :disabled="demoProcessing"
            class="px-4 py-2 rounded-lg bg-demo-blue-600 text-white text-sm font-semibold hover:bg-demo-blue-700 disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {{ demoProcessing ? 'Ingresando...' : 'Continuar ahora' }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
/* Animación sutil de entrada */
@keyframes fadeInUp {
  from { 
    opacity: 0; 
    transform: translateY(20px); 
  }
  to { 
    opacity: 1; 
    transform: translateY(0); 
  }
}

.bg-white {
  animation: fadeInUp 0.4s ease-out;
}

/* Mejora del backdrop blur en navegadores que lo soporten */
@supports (backdrop-filter: blur(20px)) {
  .bg-gradient-to-br {
    backdrop-filter: blur(20px);
  }
}
</style>
