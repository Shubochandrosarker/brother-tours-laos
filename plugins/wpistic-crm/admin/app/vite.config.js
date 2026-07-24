import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Vite emits a manifest in dist/.vite/manifest.json which PHP reads to find
// the hashed JS/CSS entry files. Keep manifest: true.
export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    manifest: true,
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: 'src/main.jsx',
    },
  },
});
