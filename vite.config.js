/**
 * Copyright (c) 2023-2026 Dartmoon S.r.l. <hello@dartmoon.io>
 * SPDX-License-Identifier: MIT
 * See LICENSE for the full license text.
 */
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
