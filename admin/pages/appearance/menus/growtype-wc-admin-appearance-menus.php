<?php

class Growtype_Wc_Admin_Appearance_Menus
{
    const NAV_CART_KEY = 'growtypeWcCart';

    public function __construct()
    {
        if (is_admin()) {
            $this->load_menus();
        }

        add_filter('wp_nav_menu_items', array ($this, 'nav_link_content'), 10, 2);
    }

    public function load_menus()
    {
        /**
         * Cart
         */
        require_once 'partials/cart.php';
        $custom_nav = new Growtype_Wc_Nav_Cart;
        add_action('admin_init', array ($custom_nav, 'add_nav_menu_meta_boxes'));
    }

    public function nav_link_content($items, $args)
    {
        if (str_contains($items, Growtype_Wc_Admin_Appearance_Menus::NAV_CART_KEY)) {
            $items_array = explode(PHP_EOL, str_replace("\r", '', $items));
            $new_items_array = array ();
            foreach ($items_array as $line) {
                if (preg_match('/<li[^>]*class="[^"]*\bgrowtype-wc-cart\b[^"]*"[^>]*>/i', $line)) {
                    $html = growtype_wc_get_cart_icon();
                    $new_items_array[] = preg_replace('/(<a[^>]*>.*?<\/a>)/is', $html, $line);
                } else {
                    $new_items_array[] = $line;
                }
            }

            $items = trim(join(PHP_EOL, $new_items_array));
        }

        return $items;
    }
}
