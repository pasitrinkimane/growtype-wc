<?php

/**
 * Include custom view
 */
add_filter('template_include', 'growtype_wc_template_include', 101);
add_filter('wc_get_template', 'growtype_wc_template_include', 1, 3);
function growtype_wc_template_include($template)
{
    if (str_contains($template, 'woocommerce/templates')) {
        $default_path = substr($template, 0, strpos($template, "templates"));
        $file_name = str_replace($default_path . 'templates/', '', $template);

        /**
         * Get local template
         */
        $local_template = GROWTYPE_WC_PATH . 'resources/views/woocommerce/' . $file_name;

        /**
         * Get local blade template
         */
        $local_blade_template = GROWTYPE_WC_PATH . 'resources/views/woocommerce/' . str_replace('.php', '.blade.php', $file_name);

        /**
         * Get child theme template
         */
        $child_template = get_stylesheet_directory() . '/views/woocommerce/' . $file_name;

        if (file_exists($child_template)) {
            $template = $child_template;
        } elseif (file_exists($local_blade_template)) {
            $template = $local_blade_template;
        } elseif (file_exists($local_template)) {
            $template = $local_template;
        }
    }

    return $template;
}

/**
 * Locate custom template
 */
add_filter('woocommerce_locate_template', 'growtype_wc_locate_template', 1, 3);
function growtype_wc_locate_template($template, $template_name, $template_path)
{
    $local_template = GROWTYPE_WC_PATH . 'resources/views/woocommerce/' . $template_name;

    /**
     * Get child theme template
     */
    $child_template = get_stylesheet_directory() . '/views/woocommerce/' . $template_name;

    if (file_exists($child_template)) {
        $template = $child_template;
    } elseif (file_exists($local_template)) {
        $template = $local_template;
    }

    return $template;
}
