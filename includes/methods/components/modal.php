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

// Allow the theme's generic growtype_get_modal lazy-loader to serve this modal.
// This makes static data-bs-target="#growtypeWcSubscriptionModal" links work
// the same way as data-bs-target="#growtypeChatStarterPackModal".
add_action('wp_ajax_growtype_get_modal',        'growtype_wc_intercept_subscription_modal_generic', 5);
add_action('wp_ajax_nopriv_growtype_get_modal', 'growtype_wc_intercept_subscription_modal_generic', 5);

function growtype_wc_intercept_subscription_modal_generic()
{
    $modal_id = ltrim($_POST['modal_id'] ?? '', '#');

    if ($modal_id !== 'growtypeWcSubscriptionModal') {
        return; // not ours — let the default handler continue
    }

    $args = apply_filters('growtype_wc_subscription_modal_args', []);
    $html = growtype_wc_include_view('components.modal.subscription', $args);

    // growtype_get_modal expects 'modal' key (vs 'html' used by our own action)
    wp_send_json_success(['modal' => $html]);
}

/**
 * Generate a consistent anchor that opens the subscription modal.
 *
 * @param string $label        The visible link text (HTML allowed).
 * @param string $modal_title  Override the title shown in the modal header.
 *                             Defaults to "Premium Feature".
 */
function growtype_wc_subscription_modal_link(string $label, string $modal_title = ''): string
{
    if (empty($modal_title)) {
        $modal_title = __('Premium Feature', 'growtype-wc');
    }

    return sprintf(
        '<a href="#" data-bs-toggle="modal" data-bs-target="#growtypeWcSubscriptionModal" data-modal-title="%s">%s</a>',
        esc_attr($modal_title),
        $label
    );
}
