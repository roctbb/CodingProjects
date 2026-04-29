import { defineConfig } from 'vite';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import path from 'path';

export default defineConfig({
  base: '/build/',
  publicDir: false,
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: false,
    sourcemap: false,
    commonjsOptions: {
      include: [/resources\/assets\/js/, /node_modules/],
      transformMixedEsModules: true,
    },
    rollupOptions: {
      input: {
        vendor: path.resolve(__dirname, 'resources/assets/js/vendor.js'),
        highlight: path.resolve(__dirname, 'resources/assets/js/highlight-legacy.js'),
        material: path.resolve(__dirname, 'resources/assets/js/material.js'),
        easymdeBridge: path.resolve(__dirname, 'resources/assets/js/easymde-bridge.js'),
        mathjaxConfig: path.resolve(__dirname, 'resources/assets/js/mathjax-config.js'),
        nbv: path.resolve(__dirname, 'resources/assets/js/nbv.js'),
        notebookRender: path.resolve(__dirname, 'resources/assets/js/notebook-render.js'),
        yandexMetrika: path.resolve(__dirname, 'resources/assets/js/yandex-metrika.js'),
        app: path.resolve(__dirname, 'resources/assets/sass/app.scss'),
        legacyTheme: path.resolve(__dirname, 'resources/assets/sass/legacy-theme.scss'),
        notebook: path.resolve(__dirname, 'resources/assets/sass/notebook.scss')
      },
      output: {
        entryFileNames: (chunk) => {
          if (chunk.name === 'vendor') {
            return 'js/vendor.js';
          }
          if (chunk.name === 'material') {
            return 'js/material.js';
          }
          if (chunk.name === 'highlight') {
            return 'js/highlight.js';
          }
          if (chunk.name === 'easymdeBridge') {
            return 'js/easymde-bridge.js';
          }
          if (chunk.name === 'mathjaxConfig') {
            return 'js/mathjax-config.js';
          }
          if (chunk.name === 'yandexMetrika') {
            return 'js/yandex-metrika.js';
          }
          if (chunk.name === 'notebookRender') {
            return 'js/notebook-render.js';
          }
          return 'js/[name].js';
        },
        chunkFileNames: 'js/chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name || '';
          if (name === 'app.css') {
            return 'css/app.css';
          }
          if (name === 'legacyTheme.css') {
            return 'css/legacy-theme.css';
          }
          if (name === 'notebook.css') {
            return 'css/notebook.css';
          }
          if (name.endsWith('.css')) {
            return 'css/[name][extname]';
          }
          if (/\.(woff2?|ttf|eot|otf)$/i.test(name)) {
            return 'fonts/[name][extname]';
          }
          if (/\.(png|jpe?g|svg|gif|webp|avif)$/i.test(name)) {
            return 'images/[name][extname]';
          }
          return 'assets/[name][extname]';
        },
      },
    },
  },
  plugins: [
    viteStaticCopy({
      targets: [
        { src: path.resolve(__dirname, 'node_modules/bootstrap-select/dist/js/bootstrap-select.min.js'), dest: 'js/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/bootstrap-select/dist/css/bootstrap-select.min.css'), dest: 'css/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/easymde/dist/easymde.min.js'), dest: 'js/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/easymde/dist/easymde.min.css'), dest: 'css/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/autosize/dist/autosize.min.js'), dest: 'js/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/dropzone/dist/min/dropzone.min.js'), dest: 'js/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/list.js/dist/list.min.js'), dest: 'js/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/flatpickr/dist/flatpickr.min.js'), dest: 'js/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/jquery-ui-dist/jquery-ui.min.js'), dest: 'js/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/jquery-ui-dist/jquery-ui.min.css'), dest: 'css/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/@shopify/draggable/build/umd/index.min.js'), dest: 'js/vendor', rename: { stripBase: true, name: 'draggable.umd.min.js' } },
        { src: path.resolve(__dirname, 'node_modules/plotly.js-dist-min/plotly.min.js'), dest: 'js/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/prismjs/prism.js'), dest: 'js/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/prismjs/themes/prism.css'), dest: 'css/vendor', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/highlight.js/styles/atom-one-light.css'), dest: 'css/vendor', rename: { stripBase: true, name: 'highlight-atom-one-light.css' } },
        { src: path.resolve(__dirname, 'public/assets/css/theme.css'), dest: 'css/legacy', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'public/assets/css/theme.css.map'), dest: 'css/legacy', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/jquery/dist/jquery.min.js'), dest: 'js/legacy', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/popper.js/dist/umd/popper.min.js'), dest: 'js/legacy', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'node_modules/bootstrap/dist/js/bootstrap.js'), dest: 'js/legacy', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'public/assets/js/swap-animation.js'), dest: 'js/legacy', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'public/assets/js/theme.js'), dest: 'js/legacy', rename: { stripBase: true } },
        { src: path.resolve(__dirname, 'public/assets/js/theme.js.map'), dest: 'js/legacy', rename: { stripBase: true } },
        { src: 'node_modules/mathjax/**/*', dest: 'js/vendor/mathjax', rename: { stripBase: 2 } },
      ],
    }),
  ],
});
