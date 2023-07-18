<?php

/**
 * @return bool
 */
function growtype_wc_format_price($string)
{
    return floatval(str_replace(',', '.', $string));
}
