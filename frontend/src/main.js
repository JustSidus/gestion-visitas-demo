import { createApp } from 'vue';
import './styles/theme.css';
import App from './App.vue';
import './assets/main.css';
import router from './router'

const app = createApp(App)
app.use(router)
app.mount('#app')


// createApp(App).mount('#app');
