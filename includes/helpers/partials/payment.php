<?php

/**
 * @return bool
 */
function growtype_wc_is_payment_page()
{
    $is_payment_page = is_checkout() && empty(is_wc_endpoint_url('order-received'));

    return apply_filters('growtype_wc_is_payment_page', $is_payment_page);
}

/**
 * @param $number
 * @return bool
 */
function growtype_wc_card_number_is_valid($number)
{
    $number = preg_replace('/\D/', '', $number);

    // Set the string length and parity
    $number_length = strlen($number);
    $parity = $number_length % 2;

    // Loop through each digit and do the maths
    $total = 0;
    for ($i = 0; $i < $number_length; $i++) {
        $digit = $number[$i];
        // Multiply alternate digits by two
        if ($i % 2 == $parity) {
            $digit *= 2;
            // If the sum is two digits, add them together (in effect)
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        // Total up the digits
        $total += $digit;
    }

    // If the total mod 10 equals 0, the number is valid
    return ($total % 10 == 0) ? true : false;
}

/**
 * @param $date
 * @return bool
 */
function growtype_wc_card_expiry_is_valid($date)
{
    $date = preg_replace('/\s+/', '', $date);
    $expMonth = explode('/', $date)[0] ?? '';

    if ($expMonth > 12) {
        return false;
    }

    $expYear = explode('/', $date)[1] ?? '';
    $expires = \DateTime::createFromFormat('my', $expMonth . $expYear);
    $now = new \DateTime();

    if ($expires < $now) {
        return false;
    }

    return true;
}

function growtype_wc_payment_method_is_enabled($payment_method_id)
{
    $payment_gateways = WC()->payment_gateways->payment_gateways();

    if (isset($payment_gateways[$payment_method_id])) {
        $gateway = $payment_gateways[$payment_method_id];

        if ($gateway->is_available()) {
            return true;
        }
    }

    return false;
}
