<?php

/**
 * @return null
 */
add_action('woocommerce_email_sent', 'growtype_wc_woocommerce_email_sent', 10, 3);
function growtype_wc_woocommerce_email_sent($return, $id, $data)
{
    if (get_option('growtype_wc_enabled_email_logs') === 'yes') {
        error_log('-----------woocommerce_email_sent--------------');
        error_log(print_r([
            'action' => 'email',
            'return' => $return,
            'type' => $id,
//            'data' => $data
        ], true));
    }
}
