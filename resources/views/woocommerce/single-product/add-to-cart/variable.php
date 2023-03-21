<?php
/**
 * Variable product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/variable.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.5.5
 */

defined('ABSPATH') || exit;

global $product;

$variations_json = wp_json_encode($available_variations);
$variations_attr = function_exists('wc_esc_json') ? wc_esc_json($variations_json) : _wp_specialchars($variations_json,
    ENT_QUOTES, 'UTF-8', true);

do_action('woocommerce_before_add_to_cart_form'); ?>

    <form class="variations_form cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action',
        $product->get_permalink())); ?>" method="post" enctype='multipart/form-data' data-product_id="<?php echo absint($product->get_id()); ?>" data-product_variations="<?php echo $variations_attr; // WPCS: XSS ok. ?>">

        <?php do_action('woocommerce_before_variations_form'); ?>

        <?php if (empty($available_variations) && false !== $available_variations) : ?>
            <p class="stock out-of-stock"><?php echo esc_html(apply_filters('woocommerce_out_of_stock_message',
                    __('This product is currently out of stock and unavailable.', 'growtype-wc'))); ?></p>
        <?php else : ?>
            <div class="variations" data-variations-amount="<?php echo count($attributes) ?>">
                <?php
                foreach ($attributes as $attribute_name => $options) {
                    $product_attributes = get_post_meta($product->get_id(), '_product_attributes', true);
                    $default_attributes = get_post_meta($product->get_id(), '_default_attributes', true);

                    $is_visible = $product_attributes[$attribute_name]['is_visible'];
                    $default_value = $default_attributes[$attribute_name];

                    if (!$is_visible) {
                        continue;
                    }

                    ?>
                    <div class="variations-single <?php echo key($attributes) === $attribute_name ? 'variation-parent' : 'variation-child' ?>" data-type="<?php echo strtolower($attribute_name) ?>">
                        <div class="label">
                            <?php
                            $attribute_term = '';
                            $attribute_terms = get_terms($attribute_name);

                            $attr_radio = get_post_meta($product->get_id(), "attribute_" . $attribute_name . "_radio");
                            $attr_radio = isset($attr_radio[0]) && $attr_radio[0] === '1' ? true : false;

                            $attr_label_disabled = get_post_meta($product->get_id(), "attribute_" . $attribute_name . "_label");
                            $attr_label_disabled = isset($attr_label_disabled[0]) && $attr_label_disabled[0] === '1' ? true : false;

                            if (is_array($attribute_terms) && isset($available_variations) && !empty($available_variations)) {
                                $attribute_term_slug = $available_variations[0]['attributes']['attribute_' . strtolower($attribute_name)];
                                $attribute_term = array_filter($attribute_terms, function ($object) use ($attribute_term_slug) {
                                    return $object->slug === $attribute_term_slug;
                                });
                            }
                            if (isset($attribute_term) && is_array($attribute_term)) {
                                $attribute_term = array_reverse($attribute_term);
                                $attribute_term = array_pop($attribute_term);
                            }
                            ?>
                            <label for="<?php echo esc_attr(sanitize_title($attribute_name)); ?>">
                                <?php
                                $attribute_name_formated = $attribute_name;
                                if (strpos($attribute_name, 'size')) {
                                    $attribute_name_formated = __('Size', 'growtype-wc');
                                }
                                ?>
                                <span class="e-type"><?php echo wc_attribute_label($attribute_name_formated) ?></span>:
                                <span class="e-name"></span>
                            </label>
                        </div>
                        <div class="options" data-type="<?php echo $attr_radio ? 'radio' : 'select' ?>">
                            <?php if ($attr_radio) {

                                $existingAttributes = [];

                                if (isset($available_variations) && !empty($available_variations)) {
                                    foreach ($available_variations as $key => $variation) {
                                        if (isset($variation['attributes'])) {
                                            $attributeTitle = $variation['attributes']['attribute_' . strtolower($attribute_name)];
                                            $termId = array_filter(wc_get_product_terms($product->get_id(), $attribute_name),
                                                function ($object) use ($attributeTitle) {
                                                    return $object->slug === $attributeTitle;
                                                });
                                            $termName = !empty($termId) && !empty(array_values($termId)) ? get_term(array_values($termId)[0]->term_id)->name : '';
                                            $termName = !empty($termName) ? $termName : $attributeTitle;
                                            $isVisible = false;
                                            $variation_value = apply_filters('woocommerce_variation_option_name', $attributeTitle);

                                            if (!in_array($attributeTitle, $existingAttributes)) {
                                                array_push($existingAttributes, $attributeTitle);
                                                $isVisible = true;
                                            }

                                            $termColor = isset(get_term_meta(array_values($termId)[0]->term_id)['color']) ? get_term_meta(array_values($termId)[0]->term_id)['color'][0] : null;

                                            ?>
                                            <div class="option <?php echo $variation_value === $default_value ? 'is-active' : '' ?>"
                                                 style="<?php echo $isVisible ? 'display:block;' : 'display:none;' ?>;background: <?php echo $termColor ?>"
                                                 data-type="<?php echo $variation_value ?>">
                                                <input type="radio"
                                                       value="<?php echo $variation['variation_id'] ?>" <?php checked($variation_value === $default_value) ?>
                                                       data-term-name="<?php echo $termName ?>"
                                                       data-category="<?php echo $variation_value ?>"
                                                       name="attribute_<?php echo sanitize_title($attribute_name) ?>">
                                                <?php if (!$attr_label_disabled) { ?>
                                                    <div class="e-label">
                                                        <span><?php echo $termName ?></span>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <?php
                                        }
                                    }
                                }

                            } else {
                                wc_dropdown_variation_attribute_options(
                                    array (
                                        'options' => $options,
                                        'attribute' => $attribute_name,
                                        'product' => $product,
                                    )
                                );
                            } ?>
                        </div>
                    </div>
                <?php } ?>
                <div class="variations-single-description" style="display: none;">
                    <div class="variations-single-description-content"></div>
                </div>
            </div>

            <div class="single_variation_wrap">
                <?php
                /**
                 * Hook: woocommerce_before_single_variation.
                 */
                do_action('woocommerce_before_single_variation');

                /**
                 * Hook: woocommerce_single_variation. Used to output the cart button and placeholder for variation data.
                 *
                 * @since 2.4.0
                 * @hooked woocommerce_single_variation - 10 Empty div for variation data.
                 * @hooked woocommerce_single_variation_add_to_cart_button - 20 Qty and cart button.
                 */
                do_action('woocommerce_single_variation');

                /**
                 * Hook: woocommerce_after_single_variation.
                 */
                do_action('woocommerce_after_single_variation');
                ?>
            </div>
        <?php endif; ?>

        <?php do_action('woocommerce_after_variations_form'); ?>
    </form>

    <script type="text/javascript">
        var product_variations_<?php echo get_the_ID() ?>= <?php echo json_encode($available_variations) ?>
    </script>

<?php
do_action('woocommerce_after_add_to_cart_form');
