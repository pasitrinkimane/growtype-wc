<?php

/**
 * Include custom view
 */
if (!function_exists('growtype_wc_include_view')) {
    function growtype_wc_include_view($file_path, $variables = array ())
    {
        $fallback_view = GROWTYPE_WC_PATH . 'resources/views/' . str_replace('.', '/', $file_path) . '.php';
        $fallback_blade_view = GROWTYPE_WC_PATH . 'resources/views/' . str_replace('.', '/', $file_path) . '.blade.php';
        $child_blade_view = get_stylesheet_directory() . '/views/' . GROWTYPE_WC_TEXT_DOMAIN . '/' . str_replace('.', '/', $file_path) . '.blade.php';
        $child_view = get_stylesheet_directory() . '/views/' . GROWTYPE_WC_TEXT_DOMAIN . '/' . str_replace('.', '/', $file_path) . '.php';

        $template_path = $fallback_view;

        if (file_exists($child_blade_view) && function_exists('App\template')) {
            return App\template($child_blade_view, $variables);
        } elseif (file_exists($child_view)) {
            $template_path = $child_view;
        } elseif (file_exists($fallback_blade_view)) {
            return App\template($fallback_blade_view, $variables);
        }

        if (file_exists($template_path)) {
            extract($variables);
            ob_start();
            include $template_path;
            $output = ob_get_clean();
        }

        return isset($output) ? $output : '';
    }
}
