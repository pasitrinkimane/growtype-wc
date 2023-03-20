let mix = require('laravel-mix');

mix
    .sass('resources/styles/growtype-wc.scss', 'styles')

mix.setPublicPath('./public');
mix.setResourceRoot('./')

// mix.autoload({
//     jquery: ['$', 'window.jQuery']
// })

mix
    .js('resources/scripts/wc-cart.js', 'scripts')
    .js('resources/scripts/wc-widgets.js', 'scripts')
    .js('resources/scripts/wc-wishlist.js', 'scripts')
    .js('resources/scripts/wc-checkout.js', 'scripts')
    .js('resources/scripts/wc-login.js', 'scripts')
    .js('resources/scripts/wc-coupon.js', 'scripts')
    .js('resources/scripts/growtype-wc.js', 'scripts')

    /**
     * Woocommerce fonts
     */
    .copyDirectory('resources/fonts', 'public/styles/fonts')

mix
    .sourceMaps()
    .version();
