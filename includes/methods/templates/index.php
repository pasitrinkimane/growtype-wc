<?php

add_filter('template_include', 'growtype_wc_template_include', 101);
add_filter('wc_get_template', 'growtype_wc_template_include', 1, 3);
function growtype_wc_template_include($template)
{
    if (str_contains($template, 'woocommerce/templates')) {
        $default_path = substr($template, 0, strpos($template, "templates"));
        $file_name = str_replace($default_path . 'templates/', '', $template);

        $local_template = GROWTYPE_WC_PATH . 'resources/views/woocommerce/' . $file_name;
        $local_blade_template = GROWTYPE_WC_PATH . 'resources/views/woocommerce/' . str_replace('.php', '.blade.php', $file_name);

        if (file_exists($local_blade_template)) {
            return $local_blade_template;
        } elseif (file_exists($local_template)) {
            return $local_template;
        }
    }

    return $template;
}

add_filter('woocommerce_locate_template', 'growtype_wc_locate_template', 1, 3);
function growtype_wc_locate_template($template, $template_name, $template_path)
{
    $local_template = GROWTYPE_WC_PATH . 'resources/views/woocommerce/' . $template_name;

    if (file_exists($local_template)) {
        return $local_template;
    }

    return $template;
}
