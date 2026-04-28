<?php

/**
 * @return null
 */
add_action('woocommerce_email_sent', 'growtype_wc_woocommerce_email_sent', 10, 3);
function growtype_wc_woocommerce_email_sent($return, $type, $data)
{
    if (get_option('growtype_wc_enabled_email_logs') === 'yes') {
        error_log('-----------woocommerce_email_sent TRIGGERED--------------');
        $object_id = (isset($data->object) && is_object($data->object) && method_exists($data->object, 'get_id')) ? $data->object->get_id() : null;
        
        error_log(print_r([
            'order_id' => $object_id,
            'action' => 'email',
            'return' => $return,
            'type' => $type,
//            'data' => $data
        ], true));
    }
}
