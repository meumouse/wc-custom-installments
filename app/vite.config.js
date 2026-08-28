import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  base: './',
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  build: {
    outDir: resolve(__dirname, 'dist'),
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
    rollupOptions: {
      input: {
        settings: resolve(__dirname, 'src/entries/settings.js'),
        onboarding: resolve(__dirname, 'src/entries/onboarding.js'),
        storefront: resolve(__dirname, 'src/entries/storefront.js'),
      },
      output: {
        // Fixed names: the backend versions these by filemtime instead of a hash.
        entryFileNames: '[name]/app.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name || '';

          if (name.endsWith('.css')) {
            return 'styles/[name][extname]';
          }

          // WordPress.org rejects file names carrying special characters, and
          // some dependencies ship them, so the base name is sanitized here.
          const base = (/([^/\]+?)(\.[^.]*)?$/.exec(name)?.[1] || 'asset').replace(/[^A-Za-z0-9._-]+/g, '-');

          return `assets/${base}-[hash][extname]`;
        },
      },
    },
  },
});
