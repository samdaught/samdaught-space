import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

export default defineConfig(({ command }) => ({
  plugins: [vue(), vueDevTools()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  input: 'src/main.ts',
  base: command === 'build' ? '/vue-dist/' : '/',

  server: {
    port: 5173,
    strictPort: true,
    cors: { origin: 'http://localhost:8000' },
    origin: 'http://localhost:5173',
  },

  build: {
    outDir: '../web/vue-dist',
    emptyOutDir: true,
    manifest: true,
  },
}))
