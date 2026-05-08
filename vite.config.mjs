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
        courseStats: path.resolve(__dirname, 'resources/assets/js/course-stats.js'),
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
          if (chunk.name === 'vendor') return 'js/vendor.js';
          if (chunk.name === 'courseStats') return 'js/course-stats.js';
          if (chunk.name === 'easymdeBridge') return 'js/easymde-bridge.js';
          if (chunk.name === 'mathjaxConfig') return 'js/mathjax-config.js';
          if (chunk.name === 'yandexMetrika') return 'js/yandex-metrika.js';
          if (chunk.name === 'notebookRender') return 'js/notebook-render.js';
          return 'js/[name].js';
        },
        chunkFileNames: 'js/chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name || '';
          if (name === 'app.css') return 'css/app.css';
          if (name === 'legacyTheme.css') return 'css/legacy-theme.css';
          if (name === 'notebook.css') return 'css/notebook.css';
          if (name.endsWith('.css')) return 'css/[name][extname]';
          if (/\.(woff2?|ttf|eot|otf)$/i.test(name)) return 'fonts/[name][extname]';
          if (/\.(png|jpe?g|svg|gif|webp|avif)$/i.test(name)) return 'images/[name][extname]';
          return 'assets/[name][extname]';
        },
      },
    },
  },
  plugins: [
    viteStaticCopy({
      targets: [
        { src: 'node_modules/easymde/dist/easymde.min.js', dest: 'js/vendor', rename: { stripBase: true } },
        { src: 'node_modules/autosize/dist/autosize.min.js', dest: 'js/vendor', rename: { stripBase: true } },
        { src: 'node_modules/dropzone/dist/min/dropzone.min.js', dest: 'js/vendor', rename: { stripBase: true } },
        { src: 'node_modules/list.js/dist/list.min.js', dest: 'js/vendor', rename: { stripBase: true } },
        { src: 'node_modules/flatpickr/dist/flatpickr.min.js', dest: 'js/vendor', rename: { stripBase: true } },
        { src: 'node_modules/plotly.js-dist-min/plotly.min.js', dest: 'js/vendor', rename: { stripBase: true } },
        { src: 'node_modules/prismjs/prism.js', dest: 'js/vendor', rename: { stripBase: true } },
        { src: 'node_modules/mathjax/**/*', dest: 'js/vendor/mathjax', rename: { stripBase: 2 } },
      ],
    }),
  ],
});
