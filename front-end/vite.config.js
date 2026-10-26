import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// /api 同源代理到 Laravel 后端：避免跨域 Cookie（Session 登录态）问题
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
})
