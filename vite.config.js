import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
  plugins: [svelte(), tailwindcss()],
  resolve: {
    alias: {
      $lib: path.resolve('./admin/src/lib'),
    },
  },
  build: {
    outDir: 'admin/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: 'admin/src/main.js',
      output: {
        entryFileNames: 'tekton-admin.js',
        chunkFileNames: 'tekton-[name].js',
        assetFileNames: 'tekton-[name].[ext]',
      },
    },
  },
  server: {
    origin: 'http://localhost:5173',
  },
});
