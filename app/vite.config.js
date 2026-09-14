import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Keep only the WOFF2 source in Font Awesome's @font-face rules.
 *
 * Each rule lists WOFF2 first and a TrueType copy as a fallback. Every browser
 * the storefront supports reads WOFF2, so the TrueType files would never be
 * downloaded and would only add some 700 KB to the plugin package.
 *
 * @since 6.0.0
 */
const fontAwesomeWoff2Only = {
  name: 'wc-custom-installments:font-awesome-woff2-only',
  enforce: 'pre',
  transform(code, id) {
    if (!/[\\/]@fortawesome[\\/]fontawesome-free[\\/]css[\\/][^\\/]+\.css$/.test(id.split('?')[0])) {
      return null;
    }

    return {
      code: code.replace(/,\s*url\(\s*["']?[^"')]+\.ttf["']?\s*\)\s*format\(\s*["']truetype["']\s*\)/g, ''),
      map: null,
    };
  },
};

export default defineConfig({
  base: './',
  plugins: [vue(), fontAwesomeWoff2Only],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
      // The plugin's brand assets live at the plugin root, beside the other
      // static assets, and are pulled into the bundle from there.
      '@brand': resolve(__dirname, '../assets/brand'),
    },
  },
  server: {
    fs: {
      allow: [resolve(__dirname, '..')],
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
        'addon-page': resolve(__dirname, 'src/entries/addon-page.js'),
        storefront: resolve(__dirname, 'src/entries/storefront.js'),
        // Stylesheet-only entry: the icon font the storefront uses by default.
        'font-awesome': resolve(__dirname, 'src/entries/font-awesome.js'),
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
          const base = (/([^/\\]+?)(\.[^./\\]*)?$/.exec(name)?.[1] || 'asset').replace(/[^A-Za-z0-9._-]+/g, '-');

          return `assets/${base}-[hash][extname]`;
        },
      },
    },
  },
});
