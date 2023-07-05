<?php
/**
 * Tag Cloud Widget.
 *
 * @package WooCommerce\Widgets
 * @version 3.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget product tag cloud
 */
class Custom_WC_Widget_Product_Meta_Filter extends WC_Widget
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->widget_cssclass = 'woocommerce widget_product_meta_filter';
        $this->widget_description = __('Filter product by meta values.', 'woocommerce');
        $this->widget_id = 'woocommerce_product_meta_filter';
        $this->widget_name = __('Growtype - Product Meta Filter', 'woocommerce');
        $this->settings = array (
            'title' => array (
                'type' => 'text',
                'std' => __('Product meta', 'woocommerce'),
                'label' => __('Title', 'woocommerce'),
            ),
            'meta_key' => array (
                'type' => 'text',
                'std' => __('', 'woocommerce'),
                'label' => __('Meta key', 'woocommerce'),
            ),
        );

        parent::__construct();
    }

    /**
     * Output widget.
     *
     * @param array $args Arguments.
     * @param array $instance Widget instance.
     * @see WP_Widget
     *
     */
    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', $instance['title']);

        echo $args['before_widget'];

        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        echo '<div class="b-content" data-key="' . $instance['meta_key'] . '">';

        echo apply_filters('custom_wc_widget_product_meta_filter_html', '', $args);

        echo '</div>';

        echo $args['after_widget'];
    }
}
