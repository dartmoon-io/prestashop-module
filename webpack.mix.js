let mix = require('laravel-mix');

mix.options({
   terser: {
     extractComments: false,
   },
   cssnano: {
      discardComments: { removeAll: true }
   }
});

mix.js('_dev/js/front.js', 'js/front.js')
   .js('_dev/js/back.js', 'js/back.js')
   .sass('_dev/scss/front.scss', 'css/front.css')
   .sass('_dev/scss/back.scss', 'css/back.css')
   .setPublicPath('views');