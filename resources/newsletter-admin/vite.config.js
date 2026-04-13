import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/admin/newsletter/',
  build: {
    outDir: '../../public/admin/newsletter',
    emptyOutDir: true,
  },
  server: {
    port: 5173,
    proxy: {
      '/admin/api': 'http://localhost:8080',
      '/newsletter/_internal': 'http://localhost:8080',
    },
  },
})
