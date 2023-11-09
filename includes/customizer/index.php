<?php

class Growtype_Wc_Customizer_Extend
{

    public function __construct()
    {
        add_action('customize_register', array ($this, 'add_sections'), 20);
        add_action('customize_controls_print_scripts', array ($this, 'add_scripts'), 30);

        $this->product_preview_styles = $this->get_available_product_preview_styles();
        $this->available_wc_coupons = $this->get_available_wc_coupons();
        $this->available_products = $this->get_available_products();

        add_action('customize_controls_enqueue_scripts', array ($this, 'customizer_control'));

        add_filter('growtype_customizer_extend_available_pages', array ($this, 'customizer_extend_available_pages'));

        add_filter('growtype_theme_access_customizer', array ($this, 'theme_access_customizer'), 10, 3);
    }

    function customizer_extend_available_pages($pages)
    {
        $pages['single_shop_page'] = 'Single product page (important: no id)';

        $wc_menu_items = wc_get_account_menu_items();

        foreach ($wc_menu_items as $key => $menu_item) {
            $pages[$key] = 'Account - ' . $menu_item;
        }

        return $pages;
    }

    function theme_access_customizer($wp_customize, $available_pages)
    {
        if (class_exists('Growtype_Customizer_Accesses_Section')) {
            /**
             * Login redirect
             */
            $wp_customize->add_setting('theme_access_user_must_have_products',
                array (
                    'default' => false,
                    'transport' => 'refresh',
                )
            );

            $wp_customize->add_control(new Skyrocket_Toggle_Switch_Custom_control($wp_customize, 'theme_access_user_must_have_products',
                array (
                    'label' => esc_html__('User Must Have Products'),
                    'section' => 'theme-access',
                    'description' => __('User must have specific products before continuing.', 'growtype-wc'),
                )
            ));

            /**
             * Redirect page
             */
            $wp_customize->add_setting('theme_access_must_have_products_redirect_page',
                array (
                    'default' => '',
                    'transport' => '',
                )
            );

            $wp_customize->add_control(new Skyrocket_Dropdown_Select2_Custom_Control($wp_customize, 'theme_access_must_have_products_redirect_page',
                array (
                    'label' => __('"Must have products" redirect page', 'growtype-wc'),
                    'description' => esc_html__('Redirect to specific page if user does not have specific products.', 'growtype-wc'),
                    'section' => 'theme-access',
                    'input_attrs' => array (
                        'multiselect' => false,
                    ),
                    'choices' => $available_pages
                )
            ));

            return $wp_customize;
        }
    }

    /*
* Customizer email preview
*/
    function customizer_control()
    {
        wp_enqueue_script('growtype_wc_customizer_control', GROWTYPE_WC_URL . 'admin/js/customizer-control.js', array ('jquery'), GROWTYPE_WC_VERSION);
        wp_enqueue_script('growtype_wc_customizer_control_email_preview', GROWTYPE_WC_URL . 'admin/js/customizer-control-email-preview.js', array ('jquery'), GROWTYPE_WC_VERSION);
    }

    /**
     * Wc products
     */
    function get_available_products()
    {
        $wc_products = wc_get_products(array ('limit' => -1));

        $products_map = [];
        if (!empty($wc_products)) {
            foreach ($wc_products as $product) {
                $products_map[$product->get_id()] = $product->get_title();
            }
        }

        return $products_map;
    }

    /**
     * Post types
     */
    function get_available_wc_coupons()
    {
        $args = array (
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'asc',
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
        );

        $posts_data = get_posts($args);

        $posts_data_formatted = [];
        foreach ($posts_data as $post) {
            $posts_data_formatted[$post->ID] = $post->post_title;
        }

        return $posts_data_formatted;
    }

    /**
     * Wc product preview styles
     */
    function get_available_product_preview_styles()
    {
        return array (
            'grid' => __('Grid', 'growtype-wc'),
            'list' => __('List', 'growtype-wc'),
            'table' => __('Table', 'growtype-wc')
        );
    }

    /**
     * Add settings to the customizer.
     *
     * @param WP_Customize_Manager $wp_customize Theme Customizer object.
     */
    public function add_sections($wp_customize)
    {
        $wp_customize->get_panel('woocommerce')->title = __('Store', 'growtype-wc');

        $this->add_general_page_section($wp_customize);
        $this->add_coupons_page_section($wp_customize);
        $this->add_product_page_section($wp_customize);
        $this->add_product_preview_section($wp_customize);
        $this->add_thankyou_page_section($wp_customize);
        $this->add_email_section($wp_customize);
        $this->add_wishlist_section($wp_customize);
        $this->add_cart_section($wp_customize);
        $this->add_account_section($wp_customize);
        $this->extend_product_catalog_page_section($wp_customize);
        $this->extend_checkout_page_section($wp_customize);

        $this->extend_accesses_section($wp_customize);
    }

    /**
     * @param $wp_customize
     */
    public function add_general_page_section($wp_customize)
    {
        require_once 'sections/general.php';
    }

    /**
     * @param $wp_customize
     */
    public function add_coupons_page_section($wp_customize)
    {
        require_once 'sections/coupons.php';
    }

    /**
     * @param $wp_customize
     */
    public function add_product_page_section($wp_customize)
    {
        require_once 'sections/product.php';
    }

    /**
     * Thank you page
     */

    /**
     * @param $wp_customize
     */
    public function add_thankyou_page_section($wp_customize)
    {
        require_once 'sections/thankyou.php';
    }

    /**
     * @param $wp_customize
     * Email section
     */
    public function add_wishlist_section($wp_customize)
    {
        require_once 'sections/wishlist.php';
    }

    /**
     * @param $wp_customize
     * Email section
     */
    public function add_cart_section($wp_customize)
    {
        require_once 'sections/cart.php';
    }

    /**
     * @param $wp_customize
     * Email section
     */
    public function add_account_section($wp_customize)
    {
        require_once 'sections/account.php';
    }

    /**
     * @param $wp_customize
     * Email section
     */
    public function add_product_preview_section($wp_customize)
    {
        require_once 'sections/product-preview.php';
    }

    /**
     * @param $wp_customize
     * Email section
     */
    public function add_email_section($wp_customize)
    {
        require_once 'sections/email.php';
    }

    /**
     * @param $wp_customize
     */
    public function extend_product_catalog_page_section($wp_customize)
    {
        require_once 'sections/catalog.php';
    }

    /**
     * @param $wp_customize
     */
    public function extend_checkout_page_section($wp_customize)
    {
        require_once 'sections/checkout.php';
    }

    /**
     * @param $wp_customize
     */
    public function extend_accesses_section($wp_customize)
    {
        require_once 'sections/accesses.php';
    }

    /**
     * Scripts to improve sections.
     */
    public function add_scripts()
    {
        $args = array (
            'post_type' => 'product',
            'posts_per_page' => 1
        );

        $query = new WP_Query($args);

        $productPageUrl = '#';

        while ($query->have_posts()) : $query->the_post();
            global $product;
            $productPageUrl = get_permalink();
        endwhile;

        wp_reset_query();

        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                wp.customize.section('woocommerce_product_page', function (section) {
                    section.expanded.bind(function (isExpanded) {
                        if (isExpanded) {
                            wp.customize.previewer.previewUrl.set('<?php echo esc_js($productPageUrl); ?>');
                        }
                    });
                });
            });
        </script>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                wp.customize.section('woocommerce_thankyou_page', function (section) {
                    section.expanded.bind(function (isExpanded) {
                        if (isExpanded) {
                            wp.customize.previewer.previewUrl.set('<?php echo home_url('/growtype-wc/documentation/examples/payment/success') ?>');
                        }
                    });
                });
            });
        </script>
        <script type="text/javascript">
            window.preview_order_id = '<?php echo growtype_wc_get_user_first_order() ? growtype_wc_get_user_first_order()->get_id() : '';?>'
            jQuery(document).ready(function ($) {
                wp.customize.section('woocommerce_email_page', function (section) {
                    section.expanded.bind(function (isExpanded) {
                        if (isExpanded) {
                            var template = $('#customize-control-woocommerce_email_page_template select').val();
                            var templateUrl = '<?php echo home_url('/growtype-wc/documentation/examples/email/preview?email_type=WC_Email_Customer_Processing_Order&order_id=' . (growtype_wc_get_user_first_order() ? growtype_wc_get_user_first_order()->get_id() : '')); ?>';
                            templateUrl = templateUrl.replace("WC_Email_Customer_Processing_Order", template);
                            wp.customize.previewer.previewUrl(templateUrl);
                            $("#sub-accordion-section-woocommerce_email_page li[id*='main_content']").hide();
                            $("#sub-accordion-section-woocommerce_email_page li[id*=" + template.toLowerCase() + "]").show();
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * @param $checked
     * Translate text input textarea
     */
    function woocommerce_thankyou_page_intro_content_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mod('woocommerce_thankyou_page_intro_content');
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value, true);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input textarea
     */
    function woocommerce_thankyou_page_intro_content_access_platform_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mod('woocommerce_thankyou_page_intro_content_access_platform');
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value, true);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input textarea
     */
    function woocommerce_product_page_sidebar_content_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mod('woocommerce_product_page_sidebar_content');
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value, true);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input textarea
     */
    function woocommerce_product_page_size_guide_details_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mod('woocommerce_product_page_size_guide_details');
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value, true);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input textarea
     */
    function woocommerce_product_page_payment_details_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mod('woocommerce_product_page_payment_details');
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value, true);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input copyright
     */
    function woocommerce_checkout_billing_section_title_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mods()["woocommerce_checkout_billing_section_title"];
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input copyright
     */
    function woocommerce_checkout_additional_section_title_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mods()["woocommerce_checkout_additional_section_title"] ?? '';
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input copyright
     */
    function woocommerce_checkout_account_section_title_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mods()["woocommerce_checkout_account_section_title"] ?? '';
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input copyright
     */
    function woocommerce_checkout_place_order_button_title_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mods()["woocommerce_checkout_place_order_button_title"];
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value);
        }

        return $value;
    }


    /**
     * @param $checked
     * Translate text input copyright
     */
    function woocommerce_thankyou_page_intro_title_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mods()["woocommerce_thankyou_page_intro_title"];
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input copyright
     */
    function woocommerce_checkout_intro_text_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mods()["woocommerce_checkout_intro_text"];
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input copyright
     */
    function woocommerce_product_preview_cta_label_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mods()["woocommerce_product_preview_cta_label"];
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value);
        }

        return $value;
    }

    /**
     * @param $checked
     * Translate text input copyright
     */
    function woocommerce_product_page_shop_loop_item_price_starts_from_text_translation($value)
    {
        if (class_exists('QTX_Translator')) {
            $translation = get_theme_mods()["woocommerce_product_page_shop_loop_item_price_starts_from_text"];
            return growtype_format_translation($_COOKIE['qtrans_front_language'], $translation, $value);
        }

        return $value;
    }
}
