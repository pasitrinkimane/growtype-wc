<?php

function growtype_wc_catalog_access_is_disabled()
{
    return get_theme_mod('catalog_disable_access', false) ? true : false;
}
