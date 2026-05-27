<?php

$action = 'growtype_wc_get_subscription_modal';

// Register AJAX handler — modal is loaded on demand, not in the footer.
add_action('wp_ajax_' . $action,        'growtype_wc_ajax_get_subscription_modal');
add_action('wp_ajax_nopriv_' . $action, 'growtype_wc_ajax_get_subscription_modal');

function growtype_wc_ajax_get_subscription_modal()
{
    $args = apply_filters('growtype_wc_subscription_modal_args', []);
    ob_start();
    echo growtype_wc_include_view('components.modal.subscription', $args);
    wp_send_json_success(['html' => ob_get_clean()]);
}

// Expose the action name into growtype_chat_ajax so JS can reference it without hardcoding.
add_filter('growtype_chat_ajax_data', function ($data) {
    $data['get_subscription_modal_action'] = 'growtype_wc_get_subscription_modal';
    return $data;
});
