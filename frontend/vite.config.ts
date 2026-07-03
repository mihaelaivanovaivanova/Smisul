import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
//
// No dev-server proxy: the API client (src/api/client.ts) talks directly to
// the absolute VITE_API_URL over CORS with credentials, which is what
// Sanctum's cookie-based SPA auth needs anyway (the CSRF cookie must be set
// by the real backend origin, not a proxied one).
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    strictPort: true,
  },
})
