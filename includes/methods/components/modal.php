<?php

add_action('get_footer', 'growtype_wc_render_modals');
function growtype_wc_render_modals()
{
    echo growtype_wc_include_view('components.modal.subscription');
}
