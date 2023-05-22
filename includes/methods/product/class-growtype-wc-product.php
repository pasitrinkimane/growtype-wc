<?php

/**
 * Growtype product methods
 * Requires: woocommerce plugin
 */
class Growtype_Wc_Product
{
    /**
     * @return mixed
     */
    public static function visible_attributes($product_id): array
    {
        $product = wc_get_product($product_id);

        $visible_attributes = [];
        if (!empty($product)) {
            $existing_attributes = get_post_meta($product->get_parent_id(), '_product_attributes', true);

            if (!$existing_attributes) {
                return [];
            }

            $product_attributes = $product->get_attributes();

            foreach ($existing_attributes as $key => $value) {
                if (isset($product_attributes[$key])) {
                    if (isset($value['is_visible'])) {
                        if ($value['is_visible']) {
                            $visible_attributes[$key] = $product_attributes[$key];
                        }
                    } else {
                        $visible_attributes[$key] = $value['name'];
                    }
                }
            }
        }

        return $visible_attributes;
    }

    /**
     * @return mixed
     */
    public static function preview_style($product_id): string
    {
        $product = wc_get_product($product_id);

        if (!empty($product)) {
            $preview_style = get_post_meta($product->get_id(), '_preview_style', true);
        }

        return $preview_style ?? '';
    }

    /**
     * @return void
     */
    public static function preview_image($product_id, $size = 'medium')
    {
        $_product = wc_get_product($product_id);
        $product_image = $_product->get_image();
        $gallery_type = Growtype_Wc_Product::gallery_type();

        /**
         * Check gallery type, and use featured image in different way if needed
         */
        if ($gallery_type === 'woocommerce-product-gallery-type-5') {
            $_parent_product = wc_get_product($_product->get_parent_id());

            $parent_img_details = wp_get_attachment_image_src(get_post_thumbnail_id($_product->get_parent_id()), $size);
            $variation_img_details = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), $size);

            if (!empty($variation_img_details) && !empty($parent_img_details)) {
                $variation_img_url = $variation_img_details[0];
                $parent_img_url = $parent_img_details[0];

                if ($variation_img_url !== $parent_img_url) {
                    $product_image = '<div class="product-img-wrapper">' . $_parent_product->get_image() . '<img src="' . $variation_img_url . '" class="img-variation">' . '</div>';
                }
            }
        }

        return $product_image;
    }

    /**
     * @return mixed
     */
    public static function only_as_single_purchase($product_id): string
    {
        $product = wc_get_product($product_id);

        if (!empty($product)) {
            $preview_style = get_post_meta($product->get_id(), '_only_as_single_purchase', true);
        }

        return $preview_style ?? '';
    }

    /**
     * @return mixed
     */
    public static function category_children($category_slug): array
    {
        global $product;

        $terms = wc_get_product_terms($product->get_id(), 'product_cat', ['parent' => false]);

        $parent = array_where($terms, function ($value, $key) use ($category_slug) {
            return $value->slug === $category_slug;
        });

        $parent = array_last($parent);

        if (!empty($parent)) {
            $term_id = $parent->term_id;

            $terms = wc_get_product_terms($product->get_id(), 'product_cat', [
                'child_of' => $term_id
            ]);

            return $terms;
        }

        return [];
    }

    /**
     * @return void
     */
    public static function location_country()
    {
        global $product;

        return get_post_meta($product->get_id(), '_product_location_country', true);
    }

    /**
     * @return void
     */
    public static function location_country_formatted()
    {
        $countries = WC()->countries->get_allowed_countries();

        return !empty(self::location_country()) ? $countries[self::location_country()] : '';
    }

    /**
     * @return void
     */
    public static function location_city()
    {
        global $product;

        return get_post_meta($product->get_id(), '_product_location_city', true);
    }

    /**
     * @param $category_slug
     * @param $value
     * @return string
     */
    public static function category_children_formatted($category_slug, $value = 'name')
    {
        $children_values = array_pluck(self::category_children($category_slug), $value);

        if (!empty($children_values)) {
            return implode(', ', $children_values);
        }

        return '';
    }

    /**
     * @return mixed
     */
    public static function sidebar()
    {
        return get_theme_mod('woocommerce_product_page_sidebar_enabled');
    }

    /**
     * @return mixed
     */
    public static function sidebar_content()
    {
        return get_theme_mod('woocommerce_product_page_sidebar_content');
    }

    /**
     * @param $product
     * @return mixed|string|void
     */
    public static function get_add_to_cart_btn_text($product = null, $default_label = null)
    {
        $add_to_cart_button_label = !empty($default_label) ? $default_label : __('Add to cart', 'growtype-wc');

        /**
         * Default custom label
         */
        if (!empty($default_label) && !str_contains($default_label, 'cart')) {
            $add_to_cart_button_label_custom_default = get_theme_mod('woocommerce_product_preview_cta_label');

            if (isset($add_to_cart_button_label_custom_default) && !empty($add_to_cart_button_label_custom_default)) {
                $add_to_cart_button_label = $add_to_cart_button_label_custom_default;
            }
        }

        if (empty($product)) {
            return $add_to_cart_button_label;
        }

        $add_to_cart_button_label_custom = get_post_meta($product->get_id(), '_add_to_cart_button_custom_text', true);

        if (!empty($add_to_cart_button_label_custom)) {
            return $add_to_cart_button_label_custom;
        }

        $product_type = $product->get_type();

        switch ($product_type) {
            case 'external':
                return $add_to_cart_button_label;
                break;
            case 'grouped':
                return __('Select', 'growtype-wc');
                break;
            case 'simple':
                return $add_to_cart_button_label;
                break;
            case 'variable':
                return $add_to_cart_button_label;
                break;
            default:
                return $add_to_cart_button_label;
        }
    }

    /**
     * @param $products
     * @return bool
     */
    public static function product_is_among_required_products($product_id)
    {
        $must_have_products_list = get_theme_mod('theme_access_user_must_have_products_list');
        $must_have_products_list = !empty($must_have_products_list) ? explode(',', $must_have_products_list) : [];

        return in_array($product_id, $must_have_products_list);
    }

    /**
     * @param $products
     * @return bool
     */
    public static function user_has_bought_required_products($user_id = null)
    {
        $user_id = !empty($user_id) ? $user_id : get_current_user_id();
        $must_have_products = get_theme_mod('theme_access_user_must_have_products');

        if ($must_have_products) {
            $must_have_products_list = get_theme_mod('theme_access_user_must_have_products_list');
            $must_have_products_list = !empty($must_have_products_list) ? explode(',', $must_have_products_list) : null;

            $customer_has_bought_products = Growtype_Wc_Product::user_has_bought_wc_products($user_id, $must_have_products_list);

            return $customer_has_bought_products;
        }

        return true;
    }

    /**
     * @param $products_ids
     * @param $user_var
     * @return bool
     */
    public static function user_has_bought_wc_products($user_id, $products_ids, $one_is_enough = true, $user_var = null)
    {
        global $wpdb;

        if (empty($user_var) || is_numeric($user_var)) {
            $meta_key = '_customer_user';
            $meta_value = $user_var ? (int)$user_var : (int)$user_id;
        } else {
            $meta_key = '_billing_email';
            $meta_value = sanitize_email($user_var);
        }

        $paid_statuses_list = class_exists('woocommerce') ? wc_get_is_paid_statuses() : ['completed'];
        $paid_statuses = array_map('esc_sql', $paid_statuses_list);

        $product_ids = is_array($products_ids) ? implode(',', $products_ids) : $products_ids;

        $line_meta_value = $product_ids != (0 || '') ? 'AND woim.meta_value IN (' . $product_ids . ')' : 'AND woim.meta_value != 0';

        /**
         * Number of products
         */
        $count = $wpdb->get_var("
        SELECT COUNT(p.ID) FROM {$wpdb->prefix}posts AS p
        INNER JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_items AS woi ON p.ID = woi.order_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS woim ON woi.order_item_id = woim.order_item_id
        WHERE p.post_status IN ( 'wc-" . implode("','wc-", $paid_statuses) . "' )
        AND pm.meta_key = '$meta_key'
        AND pm.meta_value = '$meta_value'
        AND woim.meta_key IN ( '_product_id', '_variation_id' ) $line_meta_value 
    ");

        if ($one_is_enough) {
            return $count > 0 ? true : false;
        }

        return $count === count($product_ids) ? true : false;
    }

    /**
     * @return void
     */
    public static function get_user_purchased_products_ids($user_id = null, $product_types = [])
    {
        $user_id = !empty($user_id) ? $user_id : get_current_user_id();

        if (empty($user_id)) {
            return null;
        }

        $customer_orders = get_posts(array (
            'numberposts' => -1,
            'post_type' => wc_get_order_types(),
            'post_status' => array_keys(wc_get_is_paid_statuses()),
            'meta_query' => array (
                array (
                    'key' => '_customer_user',
                    'value' => $user_id,
                    'compare' => 'LIKE'
                )
            )
        ));

        if (!$customer_orders) {
            return null;
        }

        $product_ids = array ();
        foreach ($customer_orders as $customer_order) {
            $order = wc_get_order($customer_order->ID);
            $items = $order->get_items();
            foreach ($items as $item) {
                $product_id = $item->get_product_id();
                $product = wc_get_product($product_id);

                if (!empty($product_types)) {
                    if (in_array($product->get_type(), $product_types)) {
                        $product_ids[] = $product_id;
                    }
                } else {
                    $product_ids[] = $product_id;
                }
            }
        }

        $ordered_products_ids = !empty($product_ids) ? array_values(array_unique($product_ids)) : [];

        /**
         * Get reserved products
         */
        $reserved_products_ids = get_posts(array (
            'numberposts' => -1,
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_query' => array (
                array (
                    'key' => '_reservation_user_id',
                    'value' => $user_id,
                    'compare' => 'LIKE'
                )
            ),
            'fields' => 'ids'
        ));

        return array_merge($ordered_products_ids, $reserved_products_ids);
    }

    /**
     * @return void
     */
    public static function get_user_created_products_ids($user_id = null)
    {
        $user_id = !empty($user_id) ? $user_id : get_current_user_id();

        if (empty($user_id)) {
            return null;
        }

        $args = array (
            'limit' => -1,
        );

        $products = wc_get_products($args);

        $product_ids = array ();
        foreach ($products as $product) {
            $creator_id = get_post_meta($product->get_id(), '_product_creator_id', true);
            if ($creator_id == $user_id) {
                array_push($product_ids, $product->get_id());
            }
        }

        return !empty($product_ids) ? array_values(array_unique($product_ids)) : null;
    }

    /**
     * @param $product_id
     * @param $user_id
     * @return false|void
     */
    public static function user_has_created_product($product_id, $user_id = null)
    {
        $user_id = !empty($user_id) ? $user_id : get_current_user_id();

        if (empty($user_id)) {
            return false;
        }

        $creator_id = get_post_meta($product_id, '_product_creator_id', true);

        return $creator_id == $user_id;
    }

    /**
     * @return void
     */
    public static function get_subscriptions_ids()
    {
        $args = array (
            'limit' => -1,
            'type' => 'subscription',
            'return' => 'ids'
        );

        $product_ids = wc_get_products($args);

        return !empty($product_ids) ? $product_ids : null;
    }

    /**
     * @return void
     */
    public static function get_user_subscriptions($user_id = null): array
    {
        $user_id = !empty($user_id) ? $user_id : get_current_user_id();

        if (empty($user_id)) {
            return [];
        }

        $user_products_ids = self::get_user_purchased_products_ids($user_id, ['subscription']);

        if (empty($user_products_ids)) {
            return [];
        }

        $products = [];
        foreach ($user_products_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product->is_type('subscription')) {
                array_push($products, $product);
            }
        }

        return !empty($products) ? $products : [];
    }

    /**
     * @param $product_id
     * @param $user_id
     * @return bool
     */
    public static function user_has_uploaded_product($product_id, $user_id = null): bool
    {
        $user_id = !empty($user_id) ? $user_id : get_current_user_id();

        if (empty($user_id)) {
            return false;
        }

        $creator_id = (int)get_post_meta($product_id, '_product_creator_id', true);

        return $creator_id === $user_id;
    }

    /**
     * @return bool
     */
    public static function product_preview_cta_enabled()
    {
        return get_theme_mod('woocommerce_product_preview_cta_btn', true);
    }

    /**
     * @return bool
     */
    public static function amount_in_units($product_id = null)
    {
        global $product;

        if ($product_id) {
            $product = wc_get_product($product_id);
        }

        if (empty($product)) {
            return null;
        }

        return get_post_meta($product->get_id(), '_amount_in_units', true);
    }

    /**
     * @return bool
     */
    public static function amount_in_units_formatted()
    {
        if (self::amount_in_units() > 0) {
            return self::amount_in_units() . ' ' . __('units', 'growtype-wc');
        }

        return '';
    }

    /**
     * @return bool
     */
    public static function amount_in_cases($product_id = null)
    {
        global $product;

        $product_id = !empty($product_id) ? $product_id : $product->get_id();

        return get_post_meta($product_id, '_amount_in_cases', true);
    }

    /**
     * @return bool
     */
    public static function cases_per_pallet($product_id = null)
    {
        global $product;

        $product_id = !empty($product_id) ? $product_id : $product->get_id();

        return get_post_meta($product_id, '_cases_per_pallet', true);
    }

    /**
     * @return bool
     */
    public static function volume()
    {
        global $product;

        return get_post_meta($product->get_id(), '_product_volume', true);
    }

    /**
     * @return bool
     */
    public static function volume_formatted()
    {
        return !empty(self::volume()) ? self::volume() . ' ' . __('L', 'growtype-wc') : '';
    }

    /**
     * @return void
     */
    public static function prepare_shipping_documents($file_names, $file_urls, $file_hashes, $file_keys)
    {
        $downloads = array ();

        if (!empty($file_urls)) {
            $file_url_size = count($file_urls);

            for ($i = 0; $i < $file_url_size; $i++) {
                if (!empty($file_urls[$i])) {
                    $downloads[] = array (
                        'name' => wc_clean($file_names[$i]),
                        'url' => wp_unslash(trim($file_urls[$i])),
                        'download_id' => wc_clean($file_hashes[$i]),
                        'key' => wc_clean($file_keys[$i]),
                    );
                }
            }
        }

        return $downloads;
    }

    /**
     * @return mixed
     */
    public static function shipping_documents($product_id = null): array
    {
        global $product;

        $product_id = !empty($product_id) ? $product_id : (!empty($product) ? $product->get_id() : null);

        if (!empty($product_id)) {
            return is_array(get_post_meta($product_id, '_shipping_documents', true)) ? get_post_meta($product_id, '_shipping_documents', true) : [];
        }

        return [];
    }

    /**
     * @return mixed
     */
    public static function preview_permalink($product_id = null): string
    {
        global $product;

        $product_id = !empty($product_id) ? $product_id : $product->get_id();

        return get_permalink($product_id) . '?customize=preview';
    }

    /**
     * @return mixed
     */
    public static function edit_permalink($product_id = null): string
    {
        global $product;

        $product_id = !empty($product_id) ? $product_id : $product->get_id();

        return get_permalink($product_id) . '?customize=edit';
    }

    /**
     * @return mixed
     */
    public static function permalink($product_id = null): string
    {
        global $product;

        $product_id = !empty($product_id) ? $product_id : $product->get_id();

        /**
         * Check if preview permalink applied
         */
        if (!empty(get_query_var('preview_permalink')) && get_query_var('preview_permalink')) {
            return self::preview_permalink($product_id);
        }

        return get_permalink($product_id);
    }

    /**
     * @param $product_id
     * @return string
     */
    public static function get_price_details($product_id = null): string
    {
        global $product;

        $product_id = !empty($product_id) ? $product_id : $product->get_id();

        if (!empty($product_id)) {
            $price_details = get_post_meta($product_id, '_price_details', true);
        }

        return $price_details ?? '';
    }

    /**
     * @param $product_id
     * @return bool
     */
    public static function price_is_hidden($product_id = null): bool
    {
        global $product;

        $product_id = !empty($product_id) ? $product_id : $product->get_id();

        if (!empty($product_id)) {
            $price_hidden = get_post_meta($product_id, '_hide_product_price', true);
        }

        return $price_hidden ?? false;
    }

    /**
     * @param $product_id
     * @return bool
     */
    public static function get_promo_label($product_id = null): string
    {
        global $product;

        $product_id = !empty($product_id) ? $product_id : $product->get_id();

        if (!empty($product_id)) {
            $promo_label = get_post_meta($product_id, '_promo_label', true);
        }

        return $promo_label ?? '';
    }

    /**
     * @param $product_id
     * @return bool
     */
    public static function get_promo_label_formatted($product_id = null): string
    {
        global $product;

        $promo_label = self::get_promo_label($product_id);

        if (!empty($promo_label)) {
            return '<span class="badge badge-promo bg-primary">' . $promo_label . '</span>';
        }

        return '';
    }

    /**
     * @param $product_id
     * @param $user_id
     * @return bool
     * @throws WC_Data_Exception
     */
    public static function reserve_for_user($product_id, $user_id)
    {
        $customer = new WC_Customer($user_id);

        $address = $customer->get_billing();

        $product = wc_get_product($product_id);

        if (empty($product)) {
            return false;
        }

        /**
         * Now we create the order
         */
        $order = wc_create_order();

        $order->add_product($product, 1);

        $order->set_address($address, 'billing');

        $order->set_customer_id($user_id);

        $order->calculate_totals();

        $order->update_status("pending", 'Imported order', true);

        update_post_meta($product->get_id(), '_is_reserved', true);
        update_post_meta($product->get_id(), '_reservation_user_id', $user_id);

        $product->set_catalog_visibility('hidden');

        Growtype_Wc_Product::add_tag($product, 'reserved');

        update_post_meta($product->get_id(), '_auction_closed', '2');

        $product->save();

        return true;
    }

    /**
     * @param $product
     * @param $tag
     * @return mixed
     */
    public static function add_tag($product, $tag)
    {
        $term = get_term_by('slug', $tag, 'product_tag');

        if (!empty($term)) {
            $requires_evaluation_tag_id = $term->term_id;
            $product_tags = $product->get_tag_ids();
            array_push($product_tags, $requires_evaluation_tag_id);
            $product->set_tag_ids($product_tags);
        }

        return $product;
    }

    /**
     * @param $product_id
     * @return array
     */
    public static function is_reserved($product_id)
    {
        $is_reserved = get_post_meta($product_id, '_is_reserved', true);

        return $is_reserved ?? false;
    }

    /**
     * @param $product_id
     * @return array
     */
    public static function is_reserved_for_user($product_id, $user_id = null)
    {
        $user_id = !empty($user_id) ? $user_id : get_current_user_id();

        if (empty($user_id)) {
            return false;
        }

        $is_reserved = self::is_reserved($product_id);
        $reservation_user_id = get_post_meta($product_id, '_reservation_user_id', true);

        if ($is_reserved && $reservation_user_id == $user_id) {
            return true;
        }

        return false;
    }

    /**
     * @return mixed
     */
    public static function catalog_default_preview_style()
    {
        return !empty(get_theme_mod('wc_catalog_products_preview_style')) ? get_theme_mod('wc_catalog_products_preview_style') : 'grid';
    }

    /**
     * @return mixed
     */
    public static function upload_page_url()
    {
        return class_exists('Growtype_Form') ? get_permalink(growtype_form_product_upload_page()) : home_url('my-account/uploaded-products/');
    }

    /**
     * @return false|mixed
     */
    public static function display_shop_catalog_sidebar()
    {
        $display = false;

        if (is_active_sidebar('sidebar-shop')) {
            $display = true;
        }

        $catalog_sidebar_enabled = get_theme_mod('catalog_sidebar_enabled', true);

        if (function_exists('is_shop') && is_shop() && !is_product_category() && !$catalog_sidebar_enabled) {
            $display = false;
        }

        return $display;
    }

    public function create($details)
    {
        $product = new WC_Product_Variable();

        $product->set_name($details['post_title']);

        $product->set_status('publish');

        $product->set_sku($details['sku']);

        if (isset($details['short_description'])) {
            $product->set_short_description($details['short_description']);
        }

        if (isset($details['description'])) {
            $product->set_description($details['description']);
        }

        $product->set_catalog_visibility('visible');

        if (isset($details['image_id'])) {
            $product->set_image_id($details['image_id']);
        }
        if (isset($details['image_url'])) {
            $image_id = $this->upload_image($details['image_url']);

            $product->set_image_id($image_id);
        }

        $product->set_gallery_image_ids($details['gallery_image_ids']);

        $product->set_default_attributes($details['default_attributes']);

        $product_id = $product->save();

        wp_set_object_terms($product_id, 'variable', 'product_type');

        /**
         * Add attributes
         */
        if (isset($details['variations'])) {
            foreach ($details['variations'] as $variation) {
                foreach ($variation['custom_variables'] as $taxonomy => $variable) {
                    wp_insert_term(ucfirst(str_replace('-', ' ', $variable)), str_replace('attribute_', '', $taxonomy), array (
                        'description' => isset($variable['description']) ? $variable['description'] : '',
                        'parent' => 0,
                        'slug' => isset($variable['slug']) ? $variable['slug'] : $variable
                    ));
                }
            }
        }

        /**
         * Add attributes existing
         */
        foreach ($details['taxonomies'] as $taxonomy => $values) {

            $terms = get_terms($taxonomy, array ('hide_empty' => false));
            $keywords = array_pluck($terms, 'slug');

            wp_set_object_terms($product_id, $keywords, $taxonomy);

            $product_attributes[$taxonomy] = array (
                'name' => $taxonomy,
                'value' => '',
//                'position' => 1,
                'is_visible' => isset($values['is_visible']) ? $values['is_visible'] : '1',
                'is_variation' => isset($values['is_variation']) ? $values['is_variation'] : '1',
                'is_taxonomy' => isset($values['is_taxonomy']) ? $values['is_taxonomy'] : '1',
            );

            if (isset($values['is_radio_select'])) {
                $custom_attribute_key = growtype_wc_format_attribute_key($taxonomy, 'is_radio_select');
                update_post_meta($product_id, $custom_attribute_key, $values['is_radio_select']);
            }

            if (isset($values['is_label_hidden'])) {
                $custom_attribute_key = growtype_wc_format_attribute_key($taxonomy, 'is_label_hidden');
                update_post_meta($product_id, $custom_attribute_key, $values['is_label_hidden']);
            }

            update_post_meta($product_id, '_product_attributes', $product_attributes);
        }

        /**
         * Add attribute custom
         */
//        $attr_label = 'Orientation';
//        $attr_slug = sanitize_title($attr_label);
//
//        $attributes_array[$attr_slug] = array (
//            'name' => $attr_label,
//            'value' => 'Horizontal | Vertical',
//            'is_visible' => '1',
//            'is_variation' => '1',
//            'is_taxonomy' => '0' // for some reason, this is really important
//        );
//
//        update_post_meta($post_id, '_product_attributes', $attributes_array);

        /**
         * Add variations
         */

        foreach ($details['variations'] as $variation_details) {
            $variation = array (
                'post_title' => $product->get_name(),
                'post_name' => 'product-' . $product_id . '-variation',
                'post_status' => 'publish',
                'post_parent' => $product_id,
                'post_type' => 'product_variation',
                'guid' => $product->get_permalink()
            );

            $variation_id = wp_insert_post($variation);

            $variation = new WC_Product_Variation($variation_id);

            foreach ($variation_details['custom_variables'] as $custom_key => $custom_value) {
                update_post_meta($variation_id, $custom_key, $custom_value);
            }

            /**
             * Update meta details
             */
            if (isset($variation_details['custom_meta'])) {
                foreach ($variation_details['custom_meta'] as $custom_key => $custom_value) {
                    update_post_meta($variation_id, 'custom_meta_' . $custom_key, $custom_value);
                }
            }

            $variation->set_regular_price($variation_details['regular_price']);
            $variation->set_price($variation_details['price']);

            if (isset($variation_details['sale_price']) && !empty($variation_details['sale_price'])) {
                $variation->set_sale_price($variation_details['sale_price']);
            }

            $variation->set_stock_quantity($variation_details['stock_qty']);
            $variation->set_stock_status($variation_details['stock_status']);
            $variation->set_description($variation_details['variation_description']);

            if (isset($variation_details['sku']) && !empty($variation_details['sku'])) {
                $variation->set_sku($variation_details['sku']);
            }

            $variation->set_image_id($variation_details['image_id']);

            $variation->save();
        }

        /**
         * Add categories
         */
        $cat_terms_ids = [];
        if (isset($details['categories']) && !empty($details['categories'])) {
            foreach ($details['categories'] as $category) {

                $parent_term = wp_insert_term($category['term'], 'product_cat', array (
                    'description' => isset($category['description']) ? $category['description'] : '',
                    'parent' => 0,
                    'slug' => isset($category['slug']) ? $category['slug'] : $category['term']
                ));

                if (is_wp_error($parent_term)) {
                    $parent_term_id = $parent_term->get_error_data();
                } else {
                    $parent_term_id = $parent_term['term_id'];
                }

                array_push($cat_terms_ids, $parent_term_id);

                $product->set_category_ids([$parent_term_id]);

                foreach ($category['children'] as $child) {
                    $child_term = wp_insert_term($child['term'], 'product_cat', array (
                        'description' => isset($child['description']) ? $child['description'] : '',
                        'parent' => $parent_term_id,
                        'slug' => isset($child['slug']) ? $child['slug'] : $child['term']
                    ));

                    if (is_wp_error($child_term)) {
                        $child_term_id = $child_term->get_error_data();
                    } else {
                        $child_term_id = $child_term['term_id'];
                    }

                    array_push($cat_terms_ids, $child_term_id);
                }
            }
        }

        $product->set_category_ids($cat_terms_ids);

        /**
         * Add tags
         */
        $tag_terms_ids = [];
        if (isset($details['tags']) && !empty($details['tags'])) {
            foreach ($details['tags'] as $tag) {
                $parent_term = wp_insert_term($tag['term'], 'product_tag', array (
                    'description' => isset($tag['description']) ? $tag['description'] : '',
                    'parent' => 0,
                    'slug' => isset($tag['slug']) ? $tag['slug'] : $tag['term']
                ));

                if (is_wp_error($parent_term)) {
                    $term_term_id = $parent_term->get_error_data();
                } else {
                    $term_term_id = $parent_term['term_id'];
                }

                array_push($tag_terms_ids, $term_term_id);
            }
        }

        $product->set_tag_ids($tag_terms_ids);

        $product->save();

        /**
         * Update meta details
         */
        if (isset($details['meta_details'])) {
            foreach ($details['meta_details'] as $meta_key => $meta_value) {
                update_post_meta($product_id, $meta_key, $meta_value);
            }
        }
    }

    public static function upload_image($image_url)
    {
        include_once(ABSPATH . 'wp-admin/includes/image.php');

        $file_details = explode('/', getimagesize($image_url)['mime']);
        $image_type = end($file_details);
        $uniq_name = date('dmY') . '' . (int)microtime(true);
        $filename = $uniq_name . '.' . $image_type;

        $uploaddir = wp_upload_dir();
        $uploadfile = $uploaddir['path'] . '/' . $filename;
        $contents = file_get_contents($image_url);
        $savefile = fopen($uploadfile, 'w');
        fwrite($savefile, $contents);
        fclose($savefile);

        $wp_filetype = wp_check_filetype(basename($filename), null);
        $attachment = array (
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => $filename,
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $uploadfile);
        $imagenew = get_post($attach_id);
        $fullsizepath = get_attached_file($imagenew->ID);
        $attach_data = wp_generate_attachment_metadata($attach_id, $fullsizepath);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    public static function gallery_type()
    {
        return get_theme_mod('woocommerce_product_page_gallery_type',
            'woocommerce-product-gallery-type-2');
    }
}
