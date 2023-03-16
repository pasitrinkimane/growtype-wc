let mix = require('laravel-mix');

mix
    .sass('resources/styles/growtype-wc-upload.scss', 'styles')

mix.setPublicPath('./public');
mix.setResourceRoot('./')

// mix.autoload({
//     jquery: ['$', 'window.jQuery']
// })

mix
    .js('resources/scripts/growtype-wc.js', 'scripts')

mix
    .sourceMaps()
    .version();
