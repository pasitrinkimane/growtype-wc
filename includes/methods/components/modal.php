<?php

add_action('get_footer', 'growtype_wc_render_modals');
function growtype_wc_render_modals()
{
    $subscription_modal_active = apply_filters('growtype_wc_subscription_modal_active', false);

    if ($subscription_modal_active) {
        $args = apply_filters('growtype_wc_subscription_modal_args', []);
        echo growtype_wc_include_view('components.modal.subscription', $args);
    }
}
