import {defineConfig} from 'vite';

export default defineConfig({
  base: './',
  publicDir: false,
  build: {
    outDir: 'views/assets',
    assetsDir: '',
    manifest: 'manifest.json',
    modulePreload: false,
    rolldownOptions: {
      input: [
        '_dev/scss/front.scss',
        '_dev/scss/back.scss',
        '_dev/js/front.js',
        '_dev/js/back.js',
      ],
    },
  },
});
