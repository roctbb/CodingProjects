let mix = require('laravel-mix');














mix.js('resources/assets/js/vendor.js', 'public/js/vendor.js')
   .styles([
      'node_modules/bootstrap/dist/css/bootstrap.min.css',
      'node_modules/@fortawesome/fontawesome-free/css/all.min.css',
      'node_modules/@fontsource/gothic-a1/400.css',
      'node_modules/@fontsource/gothic-a1/500.css',
      'node_modules/@fontsource/gothic-a1/700.css',
      'node_modules/easymde/dist/easymde.min.css',
      'node_modules/highlight.js/styles/atelier-lakeside-light.css',
      'node_modules/flatpickr/dist/flatpickr.min.css',
      'node_modules/dropzone/dist/min/dropzone.min.css',
      'node_modules/prismjs/themes/prism.css'
   ], 'public/css/vendor.css')
   .copyDirectory('node_modules/mathjax/es5', 'public/js/mathjax')
   .copy('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', 'public/js/vendor/bootstrap.bundle.min.js')
   .copy('node_modules/flatpickr/dist/flatpickr.min.js', 'public/js/vendor/flatpickr.min.js')
   .copy('node_modules/@shopify/draggable/build/umd/index.min.js', 'public/js/vendor/draggable.umd.min.js')
   .copy('node_modules/plotly.js-dist-min/plotly.min.js', 'public/js/vendor/plotly.min.js')
   .copyDirectory('node_modules/@fontsource/gothic-a1/files', 'public/css/files')
   .copyDirectory('node_modules/@fortawesome/fontawesome-free/webfonts', 'public/webfonts')
   .js('resources/assets/js/steps_details.js', 'public/js/steps_details.js')
   .webpackConfig({
      output: {
         publicPath: "/"
      }
   })
