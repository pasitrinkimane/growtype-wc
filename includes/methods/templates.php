<?php

/**
 * Load a template.
 *
 * Handles template usage so that we can use our own templates instead of the theme's.
 *
 * Templates are in the 'templates' folder.
 * @param string $template Template to load.
 * @return string
 */

add_filter('page_template', 'growtype_wc_page_template_loader');

function growtype_wc_page_template_loader($template)
{
    return $template;
}
