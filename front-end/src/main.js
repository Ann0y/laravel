import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import './styles/main.css'

const app = createApp(App)

// 先探测会话再挂载：路由守卫与页面渲染基于就绪后的登录态
const pinia = createPinia()
app.use(pinia)

useAuthStore()
  .init()
  .finally(() => {
    app.use(router).mount('#app')
  })
