import api from "../api/api";
import logger from '../utils/logger';

// Iniciar sesión y guardar el token
export default{
    async login(email, password) {
        logger.log("Iniciando proceso de login");
        try {
            const response = await api.post('/login', { email, password });

            if (response.data.access_token) {
                logger.success("Login exitoso");
                // Usar 'access_token' como llave única en toda la app
                localStorage.setItem('access_token', response.data.access_token);
                // También guardar role si viene
                if (response.data.user && response.data.user.role) {
                    localStorage.setItem('role', response.data.user.role); //guarda el rol
                }
                // Asegurar que axios instance tenga el header Authorization
                api.defaults.headers.common['Authorization'] = `Bearer ${response.data.access_token}`;
            } else {
                logger.error("El servidor no devolvió un token");
            }

            return response.data;
        } catch (error) {
            logger.error("Error en login", error.response?.data || error);
            throw error;
        }
    },
    // Cerrar sesión y eliminar el token
    async logout() {
        try {
            await api.post('/logout');
        } catch (e) {
            // No bloquear el logout por fallo en backend
            logger.warn('Logout backend failed', e);
        }

        // Limpiar credenciales locales estrictamente
        localStorage.removeItem('access_token');
        localStorage.removeItem('role');
        localStorage.removeItem('user');
        localStorage.removeItem('microsoft_access_token');
        
        // Eliminar posibles keys de MSAL si existen (localStorage)
        Object.keys(localStorage).forEach(key => {
            if (key && /msal|interaction|account/i.test(key)) {
                localStorage.removeItem(key);
            }
        });
        
        // Eliminar posibles keys de MSAL en sessionStorage
        try {
            Object.keys(sessionStorage).forEach(key => {
                if (key && /msal|interaction|account/i.test(key)) {
                    sessionStorage.removeItem(key);
                }
            });
        } catch (e) {
            logger.warn('Could not clear sessionStorage', e);
        }

        delete api.defaults.headers['Authorization'];
        
        logger.success('Logout completado - storage limpiado');
    },
    
    // Oener el rol almacenado
    async getRole() {
        return localStorage.getItem('role');
    },
    
    // Oener el usuario autenticado
    async getUser() {
        const response = await api.get('/me');
        return response.data;
    }
    ,
    // Limpiar TODO el almacenamiento del cliente: localStorage, sessionStorage, IndexedDB, Cache Storage, cookies y service workers
    async clearClientStorage() {
        try {
            // Local & session storage
            try { localStorage.clear(); } catch (e) { logger.warn('localStorage.clear failed', e); }
            try { sessionStorage.clear(); } catch (e) { logger.warn('sessionStorage.clear failed', e); }

            // Cookies (solo las que el script puede manipular)
            try {
                const cookies = document.cookie.split(';');
                for (const cookie of cookies) {
                    const eqPos = cookie.indexOf('=');
                    const name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
                    if (!name) continue;
                    // Expirar cookie en path=/ y en host
                    document.cookie = `${name}=; Max-Age=0; path=/; domain=${location.hostname};`;
                    document.cookie = `${name}=; Max-Age=0; path=/;`;
                }
            } catch (e) { logger.warn('Clearing cookies failed', e); }

            // Cache Storage
            try {
                if (window.caches && typeof window.caches.keys === 'function') {
                    const cacheNames = await caches.keys();
                    await Promise.all(cacheNames.map(name => caches.delete(name)));
                }
            } catch (e) { logger.warn('Clearing caches failed', e); }

            // IndexedDB - use indexedDB.databases() if available
            try {
                if (indexedDB && typeof indexedDB.databases === 'function') {
                    const dbs = await indexedDB.databases();
                    await Promise.all(dbs.map(db => db.name ? indexedDB.deleteDatabase(db.name) : Promise.resolve()));
                } else {
                    // Best-effort: attempt to delete common DB names used by libs
                    const probable = ['msal', 'idb', 'firebaseLocalStorageDb'];
                    for (const name of probable) {
                        try { indexedDB.deleteDatabase(name); } catch (err) { /* ignore */ }
                    }
                }
            } catch (e) { logger.warn('Clearing IndexedDB failed', e); }

            // Unregister service workers
            try {
                if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) {
                    const regs = await navigator.serviceWorker.getRegistrations();
                    for (const r of regs) {
                        try { await r.unregister(); } catch (err) { /* ignore */ }
                    }
                }
            } catch (e) { logger.warn('Unregistering service workers failed', e); }

            // Remove any MSAL-related keys (some libs store keys with msal prefix)
            try {
                Object.keys(localStorage).forEach(key => {
                    if (key && key.toLowerCase().startsWith('msal')) {
                        try { localStorage.removeItem(key); } catch (e) { /* ignore */ }
                    }
                });
            } catch (e) { /* ignore */ }

            logger.success('Client storage cleared');
        } catch (e) {
            logger.error('clearClientStorage error', e);
        }
    }
}
