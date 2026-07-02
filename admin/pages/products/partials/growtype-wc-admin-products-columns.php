<?php

class Growtype_Wc_Admin_Products_Columns
{
    /**
     * All product option flags to display in the column.
     * key   = post meta key
     * label = short badge label shown in the column
     */
    const OPTIONS = [
        '_virtual'                   => 'Virtual',
        '_downloadable'              => 'Downloadable',
        '_growtype_wc_subscription'  => 'Subscription',
        '_growtype_wc_trial'         => 'Trial',
        '_growtype_wc_upsell'        => 'Upsell',
    ];

    public function __construct()
    {
        add_filter('manage_product_posts_columns', [$this, 'add_column'], 20);
        add_action('manage_product_posts_custom_column', [$this, 'render_column'], 10, 2);
        add_action('admin_head', [$this, 'column_styles']);
    }

    public function add_column(array $columns): array
    {
        $new = [];

        foreach ($columns as $key => $label) {
            $new[$key] = $label;

            // Insert our column right after the product name.
            if ($key === 'name') {
                $new['product_options'] = __('Options', 'growtype-wc');
            }
        }

        // Fallback: append at end if 'name' column wasn't found.
        if (!isset($new['product_options'])) {
            $new['product_options'] = __('Options', 'growtype-wc');
        }

        return $new;
    }

    public function render_column(string $column, int $post_id): void
    {
        if ($column !== 'product_options') {
            return;
        }

        $badges = [];

        foreach (self::OPTIONS as $meta_key => $label) {
            $value = get_post_meta($post_id, $meta_key, true);

            // WooCommerce stores Virtual/Downloadable as 'yes'; custom options may be '1' or 'yes'.
            if ($value === 'yes' || $value === '1' || $value === true) {
                // Strip leading underscore, remove growtype_wc_ prefix, dashes for remaining underscores.
                $slug = ltrim($meta_key, '_');
                $slug = str_replace('growtype_wc_', '', $slug);
                $slug = sanitize_html_class(str_replace('_', '-', $slug));
                $badges[] = '<span class="gwc-option-badge gwc-option-badge--' . $slug . '">'
                    . esc_html($label)
                    . '</span>';
            }
        }

        if (!empty($badges)) {
            echo '<div class="gwc-option-badges">' . implode('', $badges) . '</div>';
        } else {
            echo '<span style="color:#bbb;">—</span>';
        }
    }

    public function column_styles(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product' || $screen->base !== 'edit') {
            return;
        }

        ?>
        <style>
            .column-product_options { width: 160px; }

            .gwc-option-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
            }

            .gwc-option-badge {
                display: inline-block;
                padding: 2px 7px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                line-height: 1.6;
                white-space: nowrap;
                background: #e5e5e5;
                color: #444;
            }

            /* Option-specific colours */
            .gwc-option-badge--virtual       { background: #dbeafe; color: #1e40af; }
            .gwc-option-badge--downloadable  { background: #dcfce7; color: #166534; }
            .gwc-option-badge--subscription  { background: #fef9c3; color: #854d0e; }
            .gwc-option-badge--trial         { background: #f3e8ff; color: #6b21a8; }
            .gwc-option-badge--upsell        { background: #fee2e2; color: #991b1b; }
        </style>
        <?php
    }
}
