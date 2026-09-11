import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // 3000 is WBL Soft's frontend port (becha-kena-erp) — Green Valley BMS
    // is a separate project and must never collide with it.
    port: 4000,
    strictPort: true,
  },
})
