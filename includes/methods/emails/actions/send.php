<?php

/**
 * @return null
 */
add_action('woocommerce_email_sent', 'growtype_wc_woocommerce_email_sent', 10, 3);
function growtype_wc_woocommerce_email_sent($return, $type, $data)
{
    if (get_option('growtype_wc_enabled_email_logs') === 'yes') {
        error_log('-----------woocommerce_email_sent TRIGGERED--------------');
        error_log(print_r([
            'order_id' => $data->object->get_id(),
            'action' => 'email',
            'return' => $return,
            'type' => $type,
//            'data' => $data
        ], true));
    }
}
