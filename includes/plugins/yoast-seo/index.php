<?php

/**
 * Meta boxes
 */
add_action('add_meta_boxes', 'growtype_wc_yoast_seo_add_meta_boxes', 100);
function growtype_wc_yoast_seo_add_meta_boxes()
{
    remove_meta_box('wpseo_meta', 'growtype_wc_subs', 'normal');
}

/**
 * Columns
 */
add_filter('manage_edit-growtype_wc_subs_columns', 'growtype_wc_yoast_seo_columns');
function growtype_wc_yoast_seo_columns($columns)
{
    unset($columns['wpseo-score']);
    unset($columns['wpseo-title']);
    unset($columns['wpseo-metadesc']);
    unset($columns['wpseo-focuskw']);
    unset($columns['wpseo-score-readability']);
    unset($columns['wpseo-linked']);
    unset($columns['wpseo-links']);
    return $columns;
}
